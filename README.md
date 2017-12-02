# French zip-code

L'objectif de ce dépôt est de maintenir une liste la plus à jour possible des **régions**, des **départements**, des **villes** et des **villages** Français.

En effet, les listes actuellement disponible sur internet ne sont visiblement pas à jour, qu'elle proviennent d'organisme tel que [data.gouv.fr](https://www.data.gouv.fr/fr/datasets/base-officielle-des-codes-postaux/) ou de site tel que [sql.sh](http://sql.sh/736-base-donnees-villes-francaises)

## Architecture

Les informations récolté sont classé dans trois documents différents et ont des liaisons entre elles afin de pouvoir remonter ou redescendre d'une liste à une autre.

### Régions (_regions_)
Pour les régions, sont listés :

| Information | Clé |
| ------------- | ------------- |
| Le nom | _name_ |
| L'identifiant | _slug_ |
| Le code iso | _iso_code_ |

### Départements (_departments_)
Pour les départements, sont listés :

| Information | Clé |
| ------------- | ------------- |
| La région de référence | _regions_id_ |
| Le code du département | _code_ |
| Le nom | _name_ |
| L'identifiant | _slug_ |
| Le code iso | _iso_code_ |

### Villes et villages (_cities_)
Pour les villes et villages, sont listés :

| Information | Clé |
| ------------- | ------------- |
| Le département de référence | _departments_id_ |
| Le nom | _name_ |
| L'identifiant | _slug_ |
| Le motif pour une recherche | _pattern_ |
| Le code postal | _postal_code_ |
| La latitude | _gps_lat_ |
| La longitude | _gps_lon_ |

## Origine des données

Les données les plus complètes et à jour que j'ai pû trouver l'ont été via [Wikipédia](https://fr.wikipedia.org/wiki/Listes_des_communes_de_France) aussi bien pour les régions que les départements ainsi que les villes.

## Participer

Vous pouvez bien entendu participer à ce dépôt afin de rester à jour et faire évoluer cette liste.