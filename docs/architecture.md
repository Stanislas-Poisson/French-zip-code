# Architecture de la refonte (ticket #55)

Ce document décrit les décisions prises pour la refonte de French-zip-code. Il a été rédigé **avant le code** et doit être validé avant toute implémentation. Les chiffres cités ont été mesurés sur les sources réelles le 2026-10-01.

## 1. Objectifs

- Produire un jeu de données propre des régions, départements, communes et codes postaux de France (métropole, DROM et COM), avec coordonnées GPS.
- Récupérer et mettre à jour les fichiers sources automatiquement.
- Garder l'historique des évolutions (fusions, créations, changements de code, suppressions) pour qu'un utilisateur puisse migrer ses propres enregistrements : à partir d'un ancien code, savoir vers quel(s) code(s) pointer aujourd'hui, avec le(s) code(s) postal(aux) correspondant(s).
- Exporter en CSV, JSON et SQL, y compris l'historique.
- Servir de **cible de clés étrangères** : une application doit pouvoir enregistrer une adresse (`3 rue Jules Massenet`) et la rattacher par une clé étrangère à l'entrée « 37200 Tours » (voir D13). Les identifiants doivent donc rester stables dans le temps.

## 2. Sources de données (vérifiées)

| Source | Contenu | Format | Rythme | Détection d'une nouvelle version |
| :--- | :--- | :--- | :--- | :--- |
| **INSEE – COG** (data.gouv.fr, jeu `58c984b088ee386cdb1261f3`) | Régions, départements, communes, COM, **événements sur les communes depuis 1943** | CSV UTF-8 | Annuel (millésime 2026 publié le 24/02/2026) | Pas d'ETag ni de Last-Modified. Lire l'API data.gouv.fr du jeu : un nouveau millésime ajoute des ressources « Millésime AAAA » |
| **La Poste – Base officielle des codes postaux** (`laposte-hexasmal`) | Lien commune ↔ code postal (39 192 lignes, 35 007 communes) | CSV `;` en **cp1252**, Licence Ouverte | Semestriel | En-tête `Last-Modified` et `dataUpdatedAt` de l'API data-fair |
| **geo.api.gouv.fr** | Communes actuelles avec centre GPS et codes postaux | JSON | Continu | En-tête `ETag` |
| **BAN – Base Adresse Nationale** (`adresse.data.gouv.fr`, un fichier par département) | Adresses avec code postal, code INSEE (et ancienne commune) et coordonnées : sert à calculer **un point GPS par couple commune + code postal** | CSV `;` gzip UTF-8, Licence Ouverte | Quotidien | En-tête `Last-Modified` de chaque fichier (un par département) |

Fichiers COG retenus (millésime M) : `v_region_M`, `v_departement_M`, `v_commune_M`, `v_commune_comer_M`, `v_comer_M`, `v_mvt_commune_M` (événements) et `v_commune_depuis_1943` (intervalles de validité de chaque code).

### Constats qui changent la conception

1. **geo.api.gouv.fr renvoie toutes les communes en une seule requête** (34 969 communes, 5,6 Mo, 0,3 s) avec centre GPS et codes postaux. Elle remplace les 35 000 appels par commune de l'ancienne version, mais **son point est celui de la commune, pas du code postal** : il ne suffit donc pas (voir 7).
2. **Les événements communaux existent déjà en base officielle** : `v_mvt_commune_2026.csv` contient 13 734 événements de 1943 à 2026, avec les codes de modalité de l'INSEE (10 changement de nom, 20 création, 21 rétablissement, 30 suppression, 31 fusion simple, 32 création de commune nouvelle, 33 fusion association, 34 fusion-association → fusion simple, 35 suppression de commune déléguée, 41 changement de code (département), 50 changement de code (chef-lieu), 70 à 72 communes déléguées). Pour les communes, **l'historique n'a pas à être reconstruit par comparaison**.
3. **Un code INSEE peut changer de sens dans le temps.** Cas Saint-Florent-des-Bois : au 2016-01-01, la commune nouvelle « Rives de l'Yon » a repris le code **85213**, qui était celui de Saint-Florent-des-Bois, et Chaillé-sous-les-Ormeaux (85043) a été absorbée. Le code 85213 existe toujours, mais désigne une autre entité. Une table `ancien code → nouveau code` ne suffit donc pas : il faut raisonner en **(code, date)**.
4. **L'INSEE ne publie aucun événement pour les départements, les régions ni les codes postaux.** Leur suivi se fait par comparaison entre deux instantanés (voir §5.3). Il est moins fiable et ne commence qu'à notre premier import.
5. **Les codes postaux sont une relation N-N.** 387 communes ont plusieurs codes postaux (jusqu'à 21), et 4 205 codes postaux sur 6 328 couvrent plusieurs communes. L'ancien modèle dupliquait la commune par code postal.
6. **geo.api.gouv.fr et La Poste divergent** sur 9 communes, toutes en outre-mer (98xxx). 5 communes de geo n'ont aucun code postal. 46 codes La Poste sont des arrondissements municipaux (Marseille, Lyon, Paris) absents des communes de geo, qui les expose séparément (`type=arrondissement-municipal`, 45 entrées).
7. **Il faut un point GPS par code postal, pas seulement par commune.** Exemple Tours (37261) : 37000, 37100 et 37200 doivent avoir chacun leur point. Deux sources testées le 2026-10-01 :
   - La Poste (`_geopoint`) donne le **même point pour les trois codes postaux** (47,3943 ; 0,6949) : inutilisable.
   - La **BAN** (Base Adresse Nationale), par département, donne des points distincts et cohérents en prenant la médiane des adresses de chaque code postal : 37000 → 47,3858 ; 0,6886 (18 746 adresses), 37100 → 47,4164 ; 0,6930 (10 489), 37200 → 47,3661 ; 0,7044 (1 007). Le fichier du département 37 pèse 9,9 Mo (275 168 adresses), toute la France 940 Mo compressés.
   - La BAN couvre la Corse, les DROM et les COM 975, 977, 978, 987 et 988. Les fichiers 984, 986 et 989 sont vides : prévoir un repli.
   - Les adresses BAN portent aussi `code_insee_ancienne_commune` : on peut rattacher un code postal à une ancienne commune fusionnée, ce qui sert directement à la résolution historique.
8. **Les communes déléguées et associées** (COMD, COMA : 2 576 lignes) ne sont pas renvoyées par geo.api en masse. Elles servent à l'historique et à la résolution, pas au jeu de données principal.

## 3. Décisions

### D1 – Sources retenues

- Structure et codes : **INSEE COG** (autorité sur les codes, noms, rattachements et événements).
- Codes postaux : **La Poste** (autorité sur le lien commune ↔ code postal), avec geo.api en contrôle.
- Coordonnées **par code postal** : **BAN** (médiane des adresses de chaque couple commune + code postal).
- Coordonnées **de la commune** : **geo.api.gouv.fr** (centre), pour les communes et en repli quand la BAN n'a aucune adresse pour un code postal.
- **Supprimés** : fichiers INSEE manuels de 2018 (`storage/builder`), regex, scraping HTML des COM, Google Maps, Nominatim, et les 35 000 appels par commune.

### D2 – Périmètre du jeu de données principal

Régions, départements (dont COM comme collectivités), communes (type `COM`) et arrondissements municipaux (type `ARM`). Les communes déléguées et associées n'y figurent pas, mais sont conservées dans l'historique.

### D3 – GPS : un point par couple commune + code postal

Chaîne de décision, du plus fiable au moins précis :

1. **BAN** (médiane des adresses du couple commune + code postal) : source principale.
2. **Nominatim** (recherche `postalcode` + `city`) pour les couples sans adresse BAN.
3. **Centre de la commune** (geo.api.gouv.fr) en dernier repli.

La colonne `coordinate_source` (`ban`, `nominatim` ou `commune_centre`) dit toujours d'où vient le point, et `address_count` donne le nombre d'adresses BAN utilisées.

**Pourquoi la BAN en premier, et pas les API.** Le test sur Tours (2026-10-01) donne :

| Code postal | BAN (médiane) | Nominatim (`postalcode`) | Géoplateforme (`municipality` + `postcode`) |
| :--- | :--- | :--- | :--- |
| 37000 | 47,3858 ; 0,6886 | 47,3847 ; 0,6905 | 47,3955 ; 0,6958 |
| 37100 | 47,4164 ; 0,6930 | 47,4189 ; 0,7024 | 47,3955 ; 0,6958 |
| 37200 | 47,3661 ; 0,7044 | 47,3659 ; 0,6889 | 47,3955 ; 0,6958 |

- La **Géoplateforme ignore le code postal** et renvoie le centre de la commune pour les trois : inutilisable ici.
- **Nominatim** donne des points distincts et proches de la BAN (200 m à 1,5 km d'écart), mais il est limité à **1 requête par seconde**. Pour les 39 192 couples cela représente environ **11 heures**, contre une passe sur 101 fichiers pour la BAN. Il dépend aussi de la couverture OpenStreetMap, et les usages massifs sont déconseillés par sa politique.
- La **BAN** est l'adresse officielle : le point est dérivé d'adresses réelles de ce code postal dans cette commune, de façon déterministe et reproductible, avec un indicateur de qualité. Ce ne sont pas des points supposés : ce sont des adresses géocodées par l'État.
- Nominatim reste utile comme **repli** pour les quelques couples sans adresse BAN. Il ne traite alors qu'un petit nombre de cas, un job par couple, avec rate limiting à 1 requête par seconde.

**Rattachement hiérarchique** : chaque `City` est liée à sa `Commune`, puis au `Department`, puis à la `Region`. On remonte et on descend l'arbre avec des relations Eloquent.

### D4 – Modèle de données

**Principe : on ne supprime et on ne recycle jamais un identifiant.** Une entité qui disparaît ou change de sens voit sa période de validité close (`valid_to`) et, si elle a un successeur, un lien vers lui. C'est ce qui permet à une application de garder ses clés étrangères et de les recoller ensuite.

- `regions` : id, code, name, slug, valid_from, valid_to.
- `departments` : id, region_id, code, name, slug, valid_from, valid_to.
- `communes` : id, department_id, insee_code, type (`COM` ou `ARM`), name, slug, centre_latitude, centre_longitude, valid_from, valid_to.
- `cities` : **une ligne par couple commune + code postal**, c'est-à-dire « 37200 Tours ». id (stable), commune_id, postal_code, label (libellé d'acheminement), latitude, longitude, address_count, coordinate_source, valid_from, valid_to, replaced_by_city_id (nullable).
- `snapshots` : id, source, version, checksum, fetched_at, imported_at, complete (booléen, voir D8).
- `commune_events` : événements INSEE bruts (modalité, date d'effet, type et code avant, type et code après, libellés), alimentés par `v_mvt_commune`.
- `commune_successions` : table dérivée utilisée par la résolution (code d'origine, validité d'origine, code d'arrivée, nature : renommée, remplacée, absorbée, scindée, supprimée, code repris, date d'effet).
- `reference_changes` : changements détectés par comparaison pour les départements, les régions et les codes postaux.

Un simple changement de nom met la ligne à jour sur place (l'ancien nom reste dans `commune_events`). Une fusion, une scission, une reprise de code ou un changement de code postal ferment la ligne et en ouvrent une nouvelle.

Clés étrangères en `*_id`, tables au pluriel, modèles au singulier (`Region`, `Department`, `Commune`, `City`), conformément au handbook. `Commune` est l'entité officielle de l'INSEE ; `City` est l'entrée postale (commune + code postal) que les adresses référencent.

#### Schéma des relations

```
                 ┌───────────┐ 1     n ┌──────────────┐ 1     n ┌───────────┐ 1     n ┌──────────┐
  Dataset        │  regions  │────────<│ departments  │────────<│ communes  │────────<│  cities  │
  (ce dépôt)     └───────────┘         └──────────────┘         └───────────┘         └────┬─────┘
                  code, name            code, name               insee_code, type          │ id (stable)
                  valid_from/to         region_id                department_id             │ postal_code
                                                                 valid_from/to             │ lat / lon
                                                                                           │ replaced_by_city_id
                                                                                           │
  Application    ┌───────────────┐ n     1 ┌────────────────────────────────────────────────┘
  tierce         │   addresses   │>────────┘
                 └───────────────┘
                  line "3 rue Jules Massenet"
                  city_id  (clé étrangère vers cities.id)

  Historique     commune_events ──► commune_successions       (par code INSEE et date, sans clé étrangère)
  (ce dépôt)     reference_changes   (départements, régions, codes postaux, comparés d'un import à l'autre)
                 snapshots           (fichier source importé : version, somme de contrôle, complet ou non)
```

- Les quatre tables du haut sont **le jeu de données** : elles se lisent de haut en bas (une région a plusieurs départements, etc.) et de bas en haut (`city->commune->department->region`).
- `addresses` n'est pas dans ce dépôt : c'est la table de l'application qui utilise le jeu de données.
- Les tables d'historique ne sont pas reliées par clé étrangère aux entités : les codes INSEE peuvent être repris ou supprimés, donc on les retrouve par (code, date).

### D5 – Algorithme de résolution d'un ancien code

Entrée : un code (INSEE ou postal), une date optionnelle (par défaut : la plus ancienne connue). Sortie : une liste de résultats.

1. Retrouver l'entité qui portait le code à la date donnée.
2. Appliquer chronologiquement les successions postérieures à cette date, en suivant les chaînes A → B → C.
3. Classer chaque branche : inchangée, renommée, remplacée (1 → 1), absorbée (N → 1), scindée (1 → N), **code repris par une autre entité** (cas 85213), **supprimée sans successeur**.
4. Pour chaque commune d'arrivée, joindre ses codes postaux actuels.

**Remonter dans le temps.** Les périodes de validité permettent de retrouver l'état à n'importe quelle date (`as_of`). Pour les communes, départements et régions, le COG fournit toute la période depuis 1943 (`v_commune_depuis_1943`, `v_mvt_commune`) et les anciens millésimes du COG (1999 à 2024) sont téléchargeables : on peut tout reconstituer. Pour les codes postaux, l'historique commence au premier instantané conservé ; l'import cherchera à l'implémentation d'éventuelles versions archivées de la base La Poste pour remonter plus loin, sans le promettre.

Une disparition sans successeur est toujours signalée explicitement (jamais renvoyée comme « inconnu »). Les résultats portent la date d'effet et la nature du changement.

### D6 – Suivi des codes postaux

Pas d'événements officiels. À chaque import La Poste, comparer avec l'instantané précédent et enregistrer dans `reference_changes` : code apparu, code disparu, commune qui change de code, code qui change de commune. **L'historique des codes postaux commence au premier instantané conservé.** Il faut le dire dans le README pour ne pas laisser croire à un suivi exhaustif.

### D7 – Pipeline de mise à jour

1. **Détection** : interroger data.gouv.fr (COG), `Last-Modified` (La Poste) et `ETag` (geo.api). Aucune action si les sommes de contrôle sont identiques.
2. **Téléchargement** : fichiers sauvegardés par version dans `storage/app/sources/{source}/{version}/`, avec somme de contrôle. Pour la BAN, un fichier par département, retéléchargé seulement si son `Last-Modified` a changé.
3. **Analyse** : lecteurs CSV et JSON en flux, un par source, derrière une interface commune. Gestion du cp1252 de La Poste.
4. **Import** : idempotent (rejouable sans doublon). Les entités, les événements et les successions sont importés dans une transaction. Les coordonnées BAN sont calculées **département par département** (voir D8), puis écrites dans `cities`.
5. **Comparaison** : calcul de `reference_changes`.
6. **Export** : voir D9.

Le tout est lancé par une commande unique, planifiable avec le scheduler Laravel (vérification quotidienne, import seulement si une source a changé).

### D8 – Jobs, Redis et Horizon

**Un job traite une unité de travail**, avec deux niveaux selon la source :

- **BAN : un job par département** (101 jobs). L'unité naturelle est le fichier du département : téléchargement, lecture en flux, calcul des médianes pour tous les couples commune + code postal de ce département.
- **Repli Nominatim : un job par couple** commune + code postal sans adresse BAN. Rate limiting à 1 requête par seconde (`Redis::throttle`).
- Les étapes se chaînent : sources officielles → import des entités → lot BAN → lot de replis → calcul des changements → exports.

**Exhaustivité garantie** : avec de la concurrence, rien ne doit être perdu en silence.

1. Avant de lancer les jobs, on construit la **liste attendue** : tous les couples (code INSEE, code postal) issus de La Poste croisés avec le COG, et tous les départements et régions du COG.
2. Chaque job enregistre son résultat (point, source ou échec explicite) ; aucun couple n'est « oublié ».
3. En fin de lot (`then` / `finally` de `Bus::batch`), un contrôle de **rapprochement** compare la liste attendue et le résultat : chaque couple doit avoir un point (BAN, Nominatim ou centre de la commune), et chaque département et région attendus doivent être présents. Les écarts sont listés.
4. Le `snapshots.complete` ne passe à vrai que si le rapprochement est total. Tant que ce n'est pas le cas, l'import n'est pas publié et les exports ne sont pas régénérés.

**Horizon** : retenu pour suivre un lot de plus de 100 jobs (progression, échecs, reprise), avec un service **Redis**. Les jobs sont idempotents et reprenables.

### D9 – Exports

Dans `Exports/` :

- Jeu courant : `regions`, `departments`, `communes`, `cities` (CSV, JSON, SQL). `cities` contient une ligne par couple commune + code postal avec latitude, longitude, nombre d'adresses et source du point, rattachée à la commune, au département et à la région.
- Historique : `commune_successions` (CSV, JSON) : *ancien code, période de validité, nouveau code, nature, date d'effet*.
- Changements par millésime : `changes/{AAAA}` (créé, supprimé, renommé, fusionné, remplacé).
- Changements de codes postaux : `city_changes` (CSV, JSON), avec l'ancien et le nouvel identifiant de `City`.

Une commande `zipcode:resolve {code} {--date=}` permet de tester la résolution. Le README décrit comment un utilisateur migre ses données avec ces fichiers.

### D10 – Découpage du code

```
app/
  Console/Commands/       # orchestration seulement (update, export, resolve)
  Contracts/              # SourceClient, SourceParser, Exporter
  Data/                   # DTO readonly (CommuneRecord, EventRecord, ...)
  Enums/                  # EventModality, SuccessionKind, EntityType
  Jobs/                   # un job par étape du pipeline
  Models/                 # Region, Department, Commune, City, ...
  Services/Sources/       # InseeCogClient, LaPosteClient, GeoApiClient
  Services/Parsers/       # parseurs CSV et JSON en flux
  Actions/                # ImportCog, ImportPostalCodes, ComputeChanges, ResolveCode, ExportDataset
```

Règles : `declare(strict_types=1)`, classes `final`, types explicites, commandes fines, logique dans des Actions (`execute()`) et des Services injectables.

### D11 – Outillage

- **PHP 8.4**, image Docker `zairakai/php` (`latest-dev` en local, `latest-test` en CI avec couverture PCOV), sans serveur web.
- **zairakai/laravel-dev-tools** : Makefile généré, Pint, PHPStan niveau max, Rector, PHPInsights, hooks git. Un Makefile projet réduit au strict nécessaire sous l'include.
- **CI GitHub Actions** appelant les cibles make du package, job nommé `ci`.
- Service **Redis** dans `docker-compose.yml` (sauf si on retient la queue `database`).

### D12 – Nettoyage

Suppression de `config/auth.php`, `mail.php`, `session.php`, des doublons `database/export/*.sql`, de `storage/builder`, de `CHANGELOG.md` (remplacé par les releases GitHub générées depuis les Conventional Commits), des dossiers `storage/framework` inutiles, et de tout `dd()` ou `DB::statement` modifiant le schéma à la volée.

### D13 – Cible d'usage : rattacher des adresses

L'objectif final est qu'une application enregistre des adresses et les relie à l'entrée postale correspondante :

```
addresses                              cities
  id                                     id            (stable, jamais recyclé)
  line  "3 rue Jules Massenet"           commune_id
  city_id  ───── clé étrangère ─────►    postal_code   "37200"
                                         label         "TOURS"
                                         latitude / longitude
```

- `cities.id` est **stable** : on n'efface jamais une ligne, on ferme sa validité (D4). Une adresse enregistrée aujourd'hui garde donc une clé valide demain.
- Quand une entrée évolue (commune fusionnée, code postal modifié), `replaced_by_city_id` et `commune_successions` donnent le nouvel `id`. La commande de résolution produit la liste « ancien `city_id` → nouveau `city_id` » pour mettre à jour les adresses (un simple `UPDATE ... JOIN`).
- Depuis une adresse, on remonte `city` → `commune` → `department` → `region`, et inversement, avec des relations Eloquent.
**Exemple de montage** (application tierce, Laravel) :

```php
// 1. Enregistrer une adresse : retrouver l'entrée « 37200 Tours » puis créer l'adresse.
$city = City::current()
    ->where('postal_code', '37200')
    ->whereRelation('commune', 'insee_code', '37261')
    ->firstOrFail();                                   // id 4821, par exemple

$address = Address::create([
    'line'    => '3 rue Jules Massenet',
    'city_id' => $city->id,
]);

// 2. Remonter l'arbre.
$address->city->postal_code;                           // 37200
$address->city->commune->name;                         // Tours
$address->city->commune->department->name;             // Indre-et-Loire
$address->city->commune->department->region->name;     // Centre-Val de Loire
$address->city->latitude;                              // 47.3661 (point du 37200)

// 3. Plus tard, après une mise à jour du jeu de données : re-pointer les adresses.
//    La ligne 4821 a été fermée (valid_to renseigné) et remplacée par la ligne 9100.
$remap = CityResolver::remap();                        // [4821 => 9100, ...] ; une disparition sans successeur est listée à part
Address::whereIn('city_id', array_keys($remap))->each(
    fn (Address $a) => $a->update(['city_id' => $remap[$a->city_id]]),
);
```

Pour que la clé étrangère `addresses.city_id → cities.id` fonctionne, **`cities` doit se trouver dans la même base de données que `addresses`**. D'où la question de la distribution (D14).

- La table `addresses` appartient à l'application utilisatrice, pas à ce jeu de données. Ce dépôt fournit les `cities` et les outils de migration. Voir la question 7 du §7.

### D14 – Distribution du jeu de données

Trois usages différents, donc trois canaux possibles :

| Canal | Pour qui | Contenu | Clé étrangère possible ? |
| :--- | :--- | :--- | :---: |
| **Fichiers CSV / JSON / SQL** (releases GitHub, data.gouv.fr) | Tout le monde, tous langages | Les tables du jeu de données | Non (hors base) |
| **Package Composer** (Packagist, namespace Zairakai) | Applications Laravel comme celle des adresses | Modèles, migrations, import, résolution ; les données se chargent dans la base de l'application | **Oui** |
| **Package npm** | Interfaces JS (auto-complétion de codes postaux) | JSON seulement | Non |

Recommandation :

1. **Ce dépôt** reste le *constructeur* : il importe, calcule, versionne et publie les fichiers. Les exports sont **attachés aux releases GitHub** (générés par la CI au tag) et publiés sur data.gouv.fr, plutôt que commités dans `Exports/` : un CSV de plusieurs Mo recommité à chaque mise à jour alourdit l'historique git.
2. **Plus tard**, un **package Composer** extrait les modèles, les migrations et l'import pour les applications Laravel. C'est lui qui répond au besoin des adresses avec clé étrangère. Il se décide après le premier import réel, quand le modèle est stabilisé.
3. **npm : pas pour l'instant.** À envisager seulement si un besoin d'auto-complétion côté navigateur apparaît.

## 4. Risques et limites

- **Historique des codes postaux partiel** : il commence au premier instantané (D6).
- **cp1252 et séparateur `;`** pour La Poste : prévoir un test d'encodage dédié.
- **geo.api et La Poste divergent** sur quelques communes d'outre-mer : la règle D1 (La Poste fait foi pour les codes postaux) doit être documentée, avec un rapport des écarts à chaque import.
- **5 communes sans code postal** dans geo.api : à signaler à l'import plutôt qu'à masquer.
- **Dépendance à la structure des fichiers INSEE** : un changement de colonnes d'un millésime à l'autre doit faire échouer l'import avec un message clair (validation de l'en-tête).
- **Volume de la BAN** : 940 Mo compressés au total. Les téléchargements sont mis en cache par département et ne sont refaits que si le fichier a changé ; la mise à jour des coordonnées peut être mensuelle plutôt que quotidienne.
- **Qualité des points BAN** : un code postal avec très peu d'adresses donne un point peu fiable. `address_count` permet de le voir et un seuil de repli peut être défini (à fixer à l'implémentation).
- **Zones sans fichier BAN** (984, 986, 989) et codes postaux sans adresse : repli Nominatim puis centre de la commune, signalé par `coordinate_source`.
- **Nominatim** : 1 requête par seconde et usage massif déconseillé ; il ne sert que de repli ciblé.
- **Licences** : COG, La Poste et la BAN sont sous Licence Ouverte ; les mentionner dans le README et les exports.

## 5. Plan d'implémentation

1. PHP 8.4, image `zairakai/php`, Redis (ou non), nettoyage de base.
2. Installation de `laravel-dev-tools`, Makefile, CI GitHub Actions.
3. Schéma, modèles et migrations.
4. Clients et parseurs des trois sources, avec tests sur de petits jeux de données de test.
5. Import du COG et construction des événements et des successions.
6. Import La Poste et geo.api, calcul des coordonnées par code postal (BAN par département, repli Nominatim, repli centre de la commune), rapprochement d'exhaustivité, calcul de `reference_changes`.
7. Résolution et commande `zipcode:resolve`.
8. Exports, README et documentation de migration pour les utilisateurs.
9. Premier import réel complet, comparaison avec le jeu publié, puis mise à jour de `Exports/`.

## 6. Hors périmètre

- Historique des codes postaux avant le premier instantané.
- Coordonnées des départements et des régions (rattachement hiérarchique seulement), sauf demande contraire.
- Interface web ou API HTTP.

## 7. Décisions à valider

1. **GPS** : BAN en premier, Nominatim en repli, centre de la commune en dernier repli : d'accord ?
2. **Modèle** : `Commune` (entité INSEE) et `City` (commune + code postal, cible des clés étrangères des adresses), avec identifiants stables et fermeture de validité plutôt que suppression : d'accord ?
3. **Queue** : Redis avec Horizon, un job par département pour la BAN et un job par couple pour le repli : d'accord ?
4. **Périmètre** : communes et arrondissements municipaux dans le jeu principal, communes déléguées et associées dans l'historique seulement : d'accord ?
5. **Historique des codes postaux** partiel (il commence au premier instantané, sauf archives retrouvées) : d'accord ?
6. **Hiérarchie** : rattachement `City` → `Commune` → `Department` → `Region` sans coordonnées pour les départements et régions : d'accord ?
7. **Adresses** : la table `addresses` reste-t-elle dans les applications utilisatrices (recommandé), ou faut-il aussi un modèle `Address` d'exemple dans ce dépôt ?
8. **Distribution** : fichiers en releases GitHub et data.gouv.fr maintenant, package Composer plus tard, npm seulement sur besoin (D14) : d'accord ?
