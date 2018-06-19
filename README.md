# French Zip-Code

## A propos
L'objectif de ce dépôt est de maintenir une liste la plus à jour possible des régions, départements, villes et villages Français en Métropole, Département et Région d'Outre-Mer (_DROM_) et Collectivités d'Outre-Mer (_COM_).

## Origine des données
Les données utilisées proviennent du site de l'INSEE :
- Métropole et DROM :
  - [_2018-03-28_ - Régions](https://www.insee.fr/fr/information/3363419#titre-bloc-26)
  - [_2018-03-28_ - Départements](https://www.insee.fr/fr/information/3363419#titre-bloc-23)
  - [_2018-03-28_ - Villes](https://www.insee.fr/fr/information/3363419#titre-bloc-7)
- COM :
  - [_2017-03-01_ - Régions, Départements, Villes](https://www.insee.fr/fr/information/2028040)

### Métropole et DROM :warning:
Les fichiers fournit sont au format _.txt_ encodés en **ISO-8859-15** avec **CRLF**. Il convient de les convertir en **UTF-8** avec **LF**.

### COM
La page indiquer est la ressource disponible, elle est donc parsée afin d'extraire la liste de entitées _Départements, villes et villages_.

## Clone, outils requis et commandes
Le dépôt utilise plusieurs technologies requises sur votre système d'exploitation :
- [Docker](https://www.docker.com/) afin de concevoir les environnements de travail (_apache, php7 et mysql_).
- [Make](http://www.gnu.org/software/make/) afin de mettre des commandes simple à disposition (_Makefile_).

- Cloner le projet dans un répertoire de votre ordinateur.
- Mettez à jour les fichiers présent dans `./storage/builder` avec les nouvelles ressources de l'INSEE :
  - cities.txt
  - departments.txt
  - regions.txt
- Editer le ficher `.env.exemple` et enregistrer le sous `.env`, modifier les différentes variables requises :
  - **APP_KEY** pour un bon fonctionnement de l'appli.
  - **GOOGLE_MAPS_KEY** une clé valide d'accès à [Google Maps API Geocoding](https://developers.google.com/maps/documentation/geocoding/start?hl=fr).
  - **COM_URI** si la ressource des COM de l'INSEE à changer.
- Dirigez vous dans son dossier en ligne de commande.
- Faite alors un `make start` qui vas lancer le projet via docker.
- Une fois le projet initialisé, faite un `make builder` qui vas lancer au travers des containers docker la récupération.
- Lorsque le build sera terminer, vous pourrez demander un export des données dans `./Exports` via la commande `make export`.

### Commandes make
- `make help` permet de lister toutes les commandes disponible.
- `make start` permet de lancer le projet.
- `make stop` permet de stopper le projet.
- `make restart` composition de `make stop` et `make start` permet de relancer le projet.
- `make builder` permet de lancer la génération des données.
- `make export` permet de lancer l'export des données générer.

## Participer
Si vous le souhaitez vous pouvez participer à ce projet en améliorant le système :
- De build utiliser par `php artisan builder:build`
- D'export utiliser par `php artisan builder:export`

## Releases
Les données sont fournit dans 3 formats (_csv, json et sql_) afin que le maximum de personnes puissent les utiliser. Les fichiers disponibles utilisent un systeme de liaison permettant de naviger facilement entre les listes utilisant les codes INSEE de l'élément cible.
Vous trouverez ci-dessous les éléments listés dans chaque fichiers.

### Régions (_regions_)
| Information | Clé |
| ------------- | ------------- |
| L'ID unique | _id_ |
| Le code INSEE de la région | _code_ |
| Le nom | _name_ |
| L'identifiant | _slug_ |

### Départements (_departments_)
| Information | Clé |
| ------------- | ------------- |
| L'ID unique | _id_ |
| La code INSEE de la région de référence | _region_code_ |
| Le code INSEE du département | _code_ |
| Le nom | _name_ |
| L'identifiant | _slug_ |

### Villes et villages (_cities_)
| Information | Clé |
| ------------- | ------------- |
| L'ID unique | _id_ |
| Le code INSEE du département de référence | _department_code_ |
| Le code INSEE de la ville / du village | _code_ |
| Le code postal | _zip_code_ |
| Le nom | _name_ |
| L'identifiant | _slug_ |
| La latitude | _gps_lat_ |
| La longitude | _gps_lng_ |

## Pourquoi ce dépôt
En effet, les listes actuellement disponible sur internet ne sont visiblement pas à jour, qu'elle proviennent d'organisme tel que [data.gouv.fr](https://www.data.gouv.fr/fr/datasets/base-officielle-des-codes-postaux/) ou de site tel que [sql.sh](http://sql.sh/736-base-donnees-villes-francaises)
