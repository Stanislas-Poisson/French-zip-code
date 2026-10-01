# Architecture de la refonte (ticket #55)

Ce document décrit les décisions prises pour la refonte de French-zip-code. Il a été rédigé **avant le code** et doit être validé avant toute implémentation. Les chiffres cités ont été mesurés sur les sources réelles le 2026-10-01.

## 1. Objectifs

- Produire un jeu de données propre des régions, départements, communes et codes postaux de France (métropole, DROM et COM), avec coordonnées GPS.
- Récupérer et mettre à jour les fichiers sources automatiquement.
- Garder l'historique des évolutions (fusions, créations, changements de code, suppressions) pour qu'un utilisateur puisse migrer ses propres enregistrements : à partir d'un ancien code, savoir vers quel(s) code(s) pointer aujourd'hui, avec le(s) code(s) postal(aux) correspondant(s).
- Exporter en CSV, JSON et SQL, y compris l'historique.

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

- **Calcul** : pour chaque couple (code INSEE, code postal), la **médiane** de la latitude et de la longitude des adresses BAN positionnées. La médiane résiste aux adresses mal placées, ce que la moyenne ne fait pas.
- **Qualité** : on stocke le nombre d'adresses utilisées (`address_count`) pour que l'utilisateur puisse juger la fiabilité d'un point (1 007 adresses pour 37200, mais parfois 3 ou 4 pour un petit code postal).
- **Repli** : si la BAN n'a aucune adresse pour un couple (communes sans fichier BAN : 984, 986, 989 ; codes postaux sans adresse), on utilise le centre de la commune fourni par geo.api, et la colonne `coordinate_source` vaut `commune_centre` au lieu de `ban`.
- **Rattachement hiérarchique** : chaque ligne est liée à sa commune, puis au département, puis à la région (`commune_postal_code` → `communes` → `departments` → `regions`). Une requête par code postal remonte donc tout l'arbre.
- Les coordonnées des départements et des régions ne sont pas calculées dans cette version (le besoin exprimé est le rattachement). À confirmer.

### D4 – Modèle de données

Toutes les entités sont versionnées par une période de validité.

- `regions` : id, code, name, slug, valid_from, valid_to.
- `departments` : id, region_id, code, name, slug, valid_from, valid_to.
- `communes` : id, department_id, insee_code, type (`COM` ou `ARM`), name, slug, latitude, longitude, valid_from, valid_to.
- `postal_codes` : id, code.
- `commune_postal_code` : commune_id, postal_code_id, latitude, longitude, address_count, coordinate_source (`ban` ou `commune_centre`), valid_from, valid_to. C'est la table centrale du jeu de données : une ligne par couple commune + code postal avec son point GPS.
- `snapshots` : id, source, version, checksum, fetched_at, imported_at (un enregistrement par fichier importé).
- `commune_events` : événements INSEE bruts (modalité, date d'effet, type et code avant, type et code après, libellés), alimentés par `v_mvt_commune`.
- `commune_successions` : table dérivée utilisée par la résolution (code d'origine, validité d'origine, code d'arrivée, nature : renommée, remplacée, absorbée, scindée, supprimée, code repris, date d'effet).
- `reference_changes` : changements détectés par comparaison pour les départements, les régions et les codes postaux (entité, type de changement, instantané avant et après).

Clés étrangères en `*_id`, tables au pluriel, modèles au singulier (`Region`, `Department`, `Commune`, `PostalCode`), conformément au handbook. **Le nom `City` devient `Commune`** pour rester fidèle au vocabulaire officiel.

### D5 – Algorithme de résolution d'un ancien code

Entrée : un code (INSEE ou postal), une date optionnelle (par défaut : la plus ancienne connue). Sortie : une liste de résultats.

1. Retrouver l'entité qui portait le code à la date donnée.
2. Appliquer chronologiquement les successions postérieures à cette date, en suivant les chaînes A → B → C.
3. Classer chaque branche : inchangée, renommée, remplacée (1 → 1), absorbée (N → 1), scindée (1 → N), **code repris par une autre entité** (cas 85213), **supprimée sans successeur**.
4. Pour chaque commune d'arrivée, joindre ses codes postaux actuels.

Une disparition sans successeur est toujours signalée explicitement (jamais renvoyée comme « inconnu »). Les résultats portent la date d'effet et la nature du changement.

### D6 – Suivi des codes postaux

Pas d'événements officiels. À chaque import La Poste, comparer avec l'instantané précédent et enregistrer dans `reference_changes` : code apparu, code disparu, commune qui change de code, code qui change de commune. **L'historique des codes postaux commence au premier instantané conservé.** Il faut le dire dans le README pour ne pas laisser croire à un suivi exhaustif.

### D7 – Pipeline de mise à jour

1. **Détection** : interroger data.gouv.fr (COG), `Last-Modified` (La Poste) et `ETag` (geo.api). Aucune action si les sommes de contrôle sont identiques.
2. **Téléchargement** : fichiers sauvegardés par version dans `storage/app/sources/{source}/{version}/`, avec somme de contrôle. Pour la BAN, un fichier par département, retéléchargé seulement si son `Last-Modified` a changé.
3. **Analyse** : lecteurs CSV et JSON en flux, un par source, derrière une interface commune. Gestion du cp1252 de La Poste.
4. **Import** : idempotent (rejouable sans doublon). Les entités, les événements et les successions sont importés dans une transaction. Les coordonnées BAN sont calculées **département par département** (voir D8), puis écrites dans `commune_postal_code`.
5. **Comparaison** : calcul de `reference_changes`.
6. **Export** : voir D9.

Le tout est lancé par une commande unique, planifiable avec le scheduler Laravel (vérification quotidienne, import seulement si une source a changé).

### D8 – Jobs, Redis et Horizon

Les coordonnées par code postal rendent les jobs **vraiment utiles** : il y a 101 fichiers BAN (environ 940 Mo compressés, 26 millions d'adresses) à télécharger et à analyser en flux, ce qui est lourd en bande passante et en processeur. Le travail se découpe naturellement **par département**.

- Un job par département (`ComputePostalCodeCoordinates`), exécutés en parallèle par plusieurs workers. Chaque job est indépendant, idempotent et reprenable.
- Les étapes sont chaînées : sources officielles (COG, La Poste, geo.api) → import des entités → lot de jobs BAN par département (`Bus::batch`) → calcul des changements → exports.
- Pas de rate limiting à gérer côté BAN (téléchargements de fichiers, pas d'appels API répétés), mais un nombre de workers raisonnable pour ne pas saturer la bande passante.
- **Horizon** : utile ici pour suivre un lot de 101 jobs (progression, échecs, reprise), contrairement à ce que je pensais avant d'avoir testé la BAN. Je le recommande, avec un **service Redis**. L'alternative sans Horizon reste de simples `queue:work` Redis.

### D9 – Exports

Dans `Exports/` :

- Jeu courant : `regions`, `departments`, `communes`, `postal_codes`, `commune_postal_code` (CSV, JSON, SQL). `commune_postal_code` contient une ligne par couple commune + code postal avec latitude, longitude, nombre d'adresses et source du point, rattachée à la commune, au département et à la région.
- Historique : `commune_successions` (CSV, JSON) : *ancien code, période de validité, nouveau code, nature, date d'effet*.
- Changements par millésime : `changes/{AAAA}` (créé, supprimé, renommé, fusionné, remplacé).
- Changements de codes postaux : `postal_code_changes` (CSV, JSON).

Une commande `zipcode:resolve {code} {--date=}` permet de tester la résolution. Le README décrit comment un utilisateur migre ses données avec ces fichiers.

### D10 – Découpage du code

```
app/
  Console/Commands/       # orchestration seulement (update, export, resolve)
  Contracts/              # SourceClient, SourceParser, Exporter
  Data/                   # DTO readonly (CommuneRecord, EventRecord, ...)
  Enums/                  # EventModality, SuccessionKind, EntityType
  Jobs/                   # un job par étape du pipeline
  Models/                 # Region, Department, Commune, PostalCode, ...
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

## 4. Risques et limites

- **Historique des codes postaux partiel** : il commence au premier instantané (D6).
- **cp1252 et séparateur `;`** pour La Poste : prévoir un test d'encodage dédié.
- **geo.api et La Poste divergent** sur quelques communes d'outre-mer : la règle D1 (La Poste fait foi pour les codes postaux) doit être documentée, avec un rapport des écarts à chaque import.
- **5 communes sans code postal** dans geo.api : à signaler à l'import plutôt qu'à masquer.
- **Dépendance à la structure des fichiers INSEE** : un changement de colonnes d'un millésime à l'autre doit faire échouer l'import avec un message clair (validation de l'en-tête).
- **Volume de la BAN** : 940 Mo compressés au total. Les téléchargements sont mis en cache par département et ne sont refaits que si le fichier a changé ; la mise à jour des coordonnées peut être mensuelle plutôt que quotidienne.
- **Qualité des points BAN** : un code postal avec très peu d'adresses donne un point peu fiable. `address_count` permet de le voir et un seuil de repli peut être défini (à fixer à l'implémentation).
- **Zones sans fichier BAN** (984, 986, 989) et codes postaux sans adresse : repli sur le centre de la commune, signalé par `coordinate_source`.
- **Licences** : COG, La Poste et la BAN sont sous Licence Ouverte ; les mentionner dans le README et les exports.

## 5. Plan d'implémentation

1. PHP 8.4, image `zairakai/php`, Redis (ou non), nettoyage de base.
2. Installation de `laravel-dev-tools`, Makefile, CI GitHub Actions.
3. Schéma, modèles et migrations.
4. Clients et parseurs des trois sources, avec tests sur de petits jeux de données de test.
5. Import du COG et construction des événements et des successions.
6. Import La Poste et geo.api, calcul des coordonnées par code postal à partir de la BAN (jobs par département), calcul de `reference_changes`.
7. Résolution et commande `zipcode:resolve`.
8. Exports, README et documentation de migration pour les utilisateurs.
9. Premier import réel complet, comparaison avec le jeu publié, puis mise à jour de `Exports/`.

## 6. Hors périmètre

- Historique des codes postaux avant le premier instantané.
- Coordonnées des départements et des régions (rattachement hiérarchique seulement), sauf demande contraire.
- Interface web ou API HTTP.

## 7. Décisions à valider

1. **GPS par code postal calculé depuis la BAN** (médiane des adresses, repli sur le centre de la commune) : d'accord ?
2. **Queue** : Redis avec Horizon pour le lot de 101 jobs par département (recommandé) ou simples workers ?
3. **Nom `Commune`** à la place de `City` dans le code, les tables et les exports : d'accord ?
4. **Périmètre** : communes et arrondissements municipaux dans le jeu principal, communes déléguées et associées dans l'historique seulement : d'accord ?
5. **Historique des codes postaux** partiel, à documenter comme tel : d'accord ?
6. **Hiérarchie** : le besoin « remonter au département puis à la région » est traité comme un **rattachement** (code postal → commune → département → région). Faut-il aussi des coordonnées pour les départements et les régions ?
