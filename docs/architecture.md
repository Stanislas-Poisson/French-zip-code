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

Fichiers COG retenus (millésime M) : `v_region_M`, `v_departement_M`, `v_commune_M`, `v_commune_comer_M`, `v_comer_M`, `v_mvt_commune_M` (événements) et `v_commune_depuis_1943` (intervalles de validité de chaque code).

### Constats qui changent la conception

1. **geo.api.gouv.fr renvoie toutes les communes en une seule requête** (34 969 communes, 5,6 Mo, 0,3 s) avec centre GPS et codes postaux. L'ancienne version faisait 35 000 appels. Plus besoin de Nominatim, de Google Maps ni de parsing d'HTML.
2. **Les événements communaux existent déjà en base officielle** : `v_mvt_commune_2026.csv` contient 13 734 événements de 1943 à 2026, avec les codes de modalité de l'INSEE (10 changement de nom, 20 création, 21 rétablissement, 30 suppression, 31 fusion simple, 32 création de commune nouvelle, 33 fusion association, 34 fusion-association → fusion simple, 35 suppression de commune déléguée, 41 changement de code (département), 50 changement de code (chef-lieu), 70 à 72 communes déléguées). Pour les communes, **l'historique n'a pas à être reconstruit par comparaison**.
3. **Un code INSEE peut changer de sens dans le temps.** Cas Saint-Florent-des-Bois : au 2016-01-01, la commune nouvelle « Rives de l'Yon » a repris le code **85213**, qui était celui de Saint-Florent-des-Bois, et Chaillé-sous-les-Ormeaux (85043) a été absorbée. Le code 85213 existe toujours, mais désigne une autre entité. Une table `ancien code → nouveau code` ne suffit donc pas : il faut raisonner en **(code, date)**.
4. **L'INSEE ne publie aucun événement pour les départements, les régions ni les codes postaux.** Leur suivi se fait par comparaison entre deux instantanés (voir §5.3). Il est moins fiable et ne commence qu'à notre premier import.
5. **Les codes postaux sont une relation N-N.** 387 communes ont plusieurs codes postaux (jusqu'à 21), et 4 205 codes postaux sur 6 328 couvrent plusieurs communes. L'ancien modèle dupliquait la commune par code postal.
6. **geo.api.gouv.fr et La Poste divergent** sur 9 communes, toutes en outre-mer (98xxx). 5 communes de geo n'ont aucun code postal. 46 codes La Poste sont des arrondissements municipaux (Marseille, Lyon, Paris) absents des communes de geo, qui les expose séparément (`type=arrondissement-municipal`, 45 entrées).
7. **Les communes déléguées et associées** (COMD, COMA : 2 576 lignes) ne sont pas renvoyées par geo.api en masse. Elles servent à l'historique et à la résolution, pas au jeu de données principal.

## 3. Décisions

### D1 – Sources retenues

- Structure et codes : **INSEE COG** (autorité sur les codes, noms, rattachements et événements).
- Codes postaux : **La Poste** (autorité sur le lien commune ↔ code postal), avec geo.api en contrôle.
- Coordonnées : **geo.api.gouv.fr** (centre de la commune).
- **Supprimés** : fichiers INSEE manuels de 2018 (`storage/builder`), regex, scraping HTML des COM, Google Maps, Nominatim.

### D2 – Périmètre du jeu de données principal

Régions, départements (dont COM comme collectivités), communes (type `COM`) et arrondissements municipaux (type `ARM`). Les communes déléguées et associées n'y figurent pas, mais sont conservées dans l'historique.

### D3 – GPS

Un seul point par commune (centre fourni par geo.api). Les coordonnées par code postal de l'ancienne version disparaissent : aucune source officielle ouverte ne les fournit, et Google Maps n'est plus utilisé. **C'est un changement incompatible à documenter.**

### D4 – Modèle de données

Toutes les entités sont versionnées par une période de validité.

- `regions` : id, code, name, slug, valid_from, valid_to.
- `departments` : id, region_id, code, name, slug, valid_from, valid_to.
- `communes` : id, department_id, insee_code, type (`COM` ou `ARM`), name, slug, latitude, longitude, valid_from, valid_to.
- `postal_codes` : id, code.
- `commune_postal_code` : commune_id, postal_code_id (relation N-N).
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
2. **Téléchargement** : fichiers sauvegardés par version dans `storage/app/sources/{source}/{version}/`, avec somme de contrôle.
3. **Analyse** : lecteurs CSV et JSON en flux, un par source, derrière une interface commune. Gestion du cp1252 de La Poste.
4. **Import** : transaction unique, idempotente (rejouable sans doublon). Remplit les entités, les événements et les successions.
5. **Comparaison** : calcul de `reference_changes`.
6. **Export** : voir D9.

Le tout est lancé par une commande unique, planifiable avec le scheduler Laravel (vérification quotidienne, import seulement si une source a changé).

### D8 – Jobs, Redis et Horizon

Comme les trois sources se récupèrent en quelques requêtes (environ 8 Mo au total), **il n'y a pas de parallélisme à gagner** sur le flux principal. Les jobs servent surtout à la robustesse : étapes reprenables, tentatives avec délai croissant, échecs visibles, exécution planifiée sans bloquer.

Recommandation :

- Jobs sur une **queue Redis**, un job par étape du pipeline, chaînés (`Bus::chain`).
- **Horizon différé** : il apporte un tableau de bord et des statistiques, mais pas de gain ici. À réévaluer si on ajoute un jour des tâches vraiment volumineuses.
- Rate limiting inutile pour le flux principal (aucun appel API répété). Il n'y en aura besoin que si on ajoute plus tard une source par commune.

**Alternative moins lourde** : queue `database` à la place de Redis, ce qui supprime un service Docker. Décision à prendre avec toi (voir §7).

### D9 – Exports

Dans `Exports/` :

- Jeu courant : `regions`, `departments`, `communes`, `postal_codes`, `commune_postal_code` (CSV, JSON, SQL).
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
- **Licences** : COG et La Poste sont sous Licence Ouverte ; les mentionner dans le README et les exports.

## 5. Plan d'implémentation

1. PHP 8.4, image `zairakai/php`, Redis (ou non), nettoyage de base.
2. Installation de `laravel-dev-tools`, Makefile, CI GitHub Actions.
3. Schéma, modèles et migrations.
4. Clients et parseurs des trois sources, avec tests sur de petits jeux de données de test.
5. Import du COG et construction des événements et des successions.
6. Import La Poste et geo.api, calcul de `reference_changes`.
7. Résolution et commande `zipcode:resolve`.
8. Exports, README et documentation de migration pour les utilisateurs.
9. Premier import réel complet, comparaison avec le jeu publié, puis mise à jour de `Exports/`.

## 6. Hors périmètre

- Historique des codes postaux avant le premier instantané.
- Coordonnées par code postal.
- Interface web ou API HTTP.

## 7. Décisions à valider

1. **Queue** : Redis avec workers simples (recommandé) ou queue `database` sans Redis ? Horizon différé : d'accord ?
2. **Nom `Commune`** à la place de `City` dans le code, les tables et les exports : d'accord ?
3. **Périmètre** : communes + arrondissements municipaux dans le jeu principal, communes déléguées et associées dans l'historique seulement : d'accord ?
4. **GPS par commune uniquement** (plus de coordonnées par code postal) : d'accord ?
5. **Historique des codes postaux partiel**, à documenter comme tel : d'accord ?
