# French Zip-Code

Jeu de données des régions, départements, communes et codes postaux de France (métropole, DROM et COM), avec **un point GPS par code postal** et l'**historique des évolutions** (fusions, changements de code, créations, suppressions).

Il est construit à partir de sources officielles ouvertes, mis à jour automatiquement, et conçu pour qu'une application puisse y rattacher ses adresses par une clé étrangère.

## Ce que contient le jeu de données

| Table | Contenu |
| :--- | :--- |
| `regions` | Les régions (code, nom). |
| `departments` | Les départements et les collectivités d'outre-mer (code, nom, région). |
| `communes` | Les communes et les arrondissements municipaux, avec leur code INSEE, leur département et le centre de la commune. |
| `cities` | **Une ligne par commune et par code postal**, par exemple « 37200 Tours », avec son point GPS. |
| `commune_successions` | Pour un ancien code INSEE, le code qui lui succède, la nature du changement et sa date. |
| `reference_changes` | Les changements détectés entre deux mises à jour (régions, départements, codes postaux). |

Toutes les tables portent une période de validité (`valid_from`, `valid_to`) : une ligne n'est jamais supprimée, sa validité est fermée. Les identifiants sont donc stables dans le temps.

```
regions ──< departments ──< communes ──< cities <── addresses (table de votre application)
```

### Un point GPS par code postal

Tours (37261) a trois codes postaux, et chacun a son point :

| Code postal | Latitude | Longitude | Adresses utilisées |
| :--- | :--- | :--- | :--- |
| 37000 | 47,3858 | 0,6886 | 18 746 |
| 37100 | 47,4164 | 0,6930 | 10 489 |
| 37200 | 47,3661 | 0,7044 | 1 007 |

Le point d'un code postal est la **médiane des positions des adresses** de la [Base Adresse Nationale](https://adresse.data.gouv.fr/) (BAN) pour ce code postal dans cette commune. La colonne `coordinate_source` indique toujours l'origine du point :

1. `ban` : médiane des adresses de la BAN (la grande majorité des villes).
2. `nominatim` : recherche du code postal et de la commune sur [Nominatim](https://nominatim.org/), pour les villes sans adresse dans la BAN.
3. `commune_centre` : centre de la commune, en dernier repli.

`address_count` donne le nombre d'adresses utilisées : un code postal avec très peu d'adresses est moins fiable.

## Sources et licences

| Source | Usage | Licence |
| :--- | :--- | :--- |
| [INSEE, Code officiel géographique](https://www.insee.fr/fr/information/8377162) | Régions, départements, communes, historique depuis 1943 et événements sur les communes. | Licence Ouverte |
| [La Poste, base officielle des codes postaux](https://data.laposte.fr/datasets/laposte-hexasmal) | Lien entre une commune et ses codes postaux. | Licence Ouverte |
| [geo.api.gouv.fr](https://geo.api.gouv.fr/) | Centre de chaque commune. | Licence Ouverte |
| [Base Adresse Nationale](https://adresse.data.gouv.fr/) | Points GPS de chaque code postal. | Licence Ouverte |
| [Nominatim](https://nominatim.org/) (OpenStreetMap) | Repli pour les villes sans adresse dans la BAN. | ODbL |

## Rattacher des adresses

Une application enregistre ses adresses avec une clé étrangère vers `cities` :

```php
$city = City::current()
    ->where('postal_code', '37200')
    ->whereRelation('commune', 'insee_code', '37261')
    ->firstOrFail();

$address = Address::create([
    'line' => '3 rue Jules Massenet',
    'city_id' => $city->id,
]);

$address->city->commune->department->region->name; // Centre-Val de Loire
```

Pour que la clé étrangère `addresses.city_id` fonctionne, la table `cities` doit se trouver dans la même base de données que `addresses`.

## Migrer d'anciens codes

Quand une commune fusionne ou change de code, ses anciens codes continuent de pointer vers elle grâce à `commune_successions`. Exemple : Saint-Florent-des-Bois a fusionné en 2016 dans « Rives de l'Yon », qui a repris le code **85213**, tandis que Chaillé-sous-les-Ormeaux (85043) a été absorbée.

```bash
make resolve CODE=85043 ZIP=85310
# 2016-01-01  absorbed: 85043 -> 85213
# 2016-01-01  code_reused: 85213 -> 85213
# Current communes: 85213
# city #33125  85310  RIVES DE L YON  (46.590263, -1.333875)
```

Le résultat suit les chaînes de succession (A vers B vers C), signale une commune **disparue sans successeur**, liste tous les successeurs d'une commune scindée et prévient quand le code postal d'origine n'est plus utilisé. Les mêmes informations sont dans les fichiers exportés (`commune_successions`, et `replaced_by_city_id` dans `cities`) pour migrer vos données sans passer par ce dépôt.

### Limites de l'historique

- Pour les communes, les départements et les régions, l'historique remonte à 1943 (les COM d'outre-mer n'en ont pas avant le premier import).
- L'INSEE ne publie aucun événement pour les **codes postaux** : leur suivi repose sur la comparaison entre deux mises à jour et **commence au premier import conservé**.

## Utilisation

Prérequis : [Docker](https://www.docker.com/) et [Make](https://www.gnu.org/software/make/).

```bash
make start      # lance PHP 8.4, MySQL 8.4, Redis et Horizon, installe les dépendances, migre la base
make update     # met à jour le jeu de données (fichiers officiels puis points GPS), sur la queue
make status     # état du jeu de données et de la dernière mise à jour
make export     # exporte en CSV, JSON et SQL dans storage/app/exports
make stop       # arrête le projet et supprime ses conteneurs et ses volumes
```

`make update` télécharge les fichiers officiels et ne réimporte que ceux qui ont changé. Les points GPS sont calculés par des jobs en parallèle, un par département, puis un contrôle vérifie que **chaque ville a un point**, que **chaque département a été traité** et qu'aucun job n'a échoué. Une mise à jour complète prend environ 15 minutes.

Les commandes `php artisan` correspondantes : `zipcode:update` (`--sync`, `--force`, `--skip-coordinates`), `zipcode:status`, `zipcode:resolve` et `zipcode:export`.

## Fichiers publiés

Les exports sont attachés aux [releases](https://github.com/Stanislas-Poisson/French-zip-code/releases) du dépôt et publiés sur [data.gouv.fr](https://www.data.gouv.fr/datasets/regions-departements-villes-et-villages-de-france-et-doutre-mer). Ils contiennent un fichier par table, en CSV, JSON et SQL.

## Développement

```bash
make quality    # Pint, PHPStan (niveau max), Rector, PHPInsights, Markdownlint
make test       # PHPUnit
```

Le dépôt utilise [`zairakai/laravel-dev-tools`](https://packagist.org/packages/zairakai/laravel-dev-tools) (outils de qualité, hooks git, Makefile). Les commits suivent les Conventional Commits avec le numéro de ticket (`type(scope): #123 sujet`) et le dépôt n'accepte que des merge commits sur des branches rebasées. L'architecture est décrite dans [`docs/architecture.md`](docs/architecture.md) et la publication d'une version dans [`docs/release.md`](docs/release.md).

## Licence

[MIT](LICENSE) pour le code. Les données restent soumises aux licences de leurs sources (voir ci-dessus).
