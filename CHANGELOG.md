# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
and this project adheres to [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2018-06-19
Changement intégrale des données d'origine, de la méthodologie de récupération ainsi que de la structure des données.
Consultez le README.md pour plus d'informations.

:warning: Compatibilité avec les versions inférieures non assuré.

------------

## [1.3.1] - 2018-05-29
### Changed
- Fixing the typographic department of _Haute-Savoie_ missing the **e** for _Haute_

## [1.3.0] - 2018-04-21
### Added
- Add this changelog

### Changed
- Fixing the format of postal_code in the cities (_sql, json, csv_) for:
  - Montrevault-sur-Èvre
  - Amiens

### Removed
- Remove the following id in the cities for duplicate:
  - 3931 : Port-la-Nouvelle
  - 18996 : Isles-sur-Suippe
  - 24683 : Hénin-sur-Cojeul

## [1.2.0] - 2018-03-14
### Changed
- Fixing the missing **ü** in the cities:
  - slug
  - pattern

## [1.1.0] - 2018-01-06
### Added
- The following cities are missing in the list:
  - Aurseulles
  - Crolles
  - Mazé-Milon

### Changed
- Fixing the format of name in the regions (_sql_) for:
  - Provence-Alpes-Côte d'Azur
- Fixing the format of name in the cities (_sql, json, csv_) for:
  - L'Aiguillon-sur-Mer
  - L'Aiguillon-sur-Vie
  - Château-d'Olonne
  - L'Épine
  - Grand'Landes
  - L'Herbergement
  - L'Hermenault
  - L'Île-d'Elle
  - L'Île-d'Olonne
  - L'Île-d'Yeu
  - Nieul-sur-l'Autise
  - Noirmoutier-en-l'Île
  - L'Orbrie
  - Rives-de-l'Yon
  - Les Sables-d'Olonne
  - Saint-André-Goule-d'Oie
  - Saint-Michel-en-l'Herm
  - Ville-d'Avray
- Fixing the format of postal_code in the cities (_sql, json, csv_) for:
  - Aurseulles
  - La Chapelle-Saint-Géraud
  - Palisse
  - Crolles
  - Férolles
  - Saint-Firmin-sur-Loire
  - Mazé-Milon
  - Amiens

## [1.0.1] - 2017-12-08
### Changed
- Fixing the export for creating the sql cities:
  - **gps_lat** passed from _double(8,2)_ to _double(9,4)_
  - **gps_lon** passed from _double(8,2)_ to _double(9,4)_

## [1.0] - 2017-12-04
### Added
- The first release of the data.
