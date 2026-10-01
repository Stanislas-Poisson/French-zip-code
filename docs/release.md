# Publier une version

Les fichiers du jeu de données sont produits par `make export` et attachés à la release GitHub. Ils ne sont pas commités dans le dépôt.

## Étapes

1. Mettre à jour le jeu de données et vérifier le rapport.

   ```bash
   make update-sync
   make status
   ```

   Le rapport doit indiquer `"complete":true` : chaque ville a un point, chaque département a été traité et aucun job n'a échoué.

2. Exporter les fichiers.

   ```bash
   make export
   ```

3. Créer le tag de version, réservé aux mainteneurs et préfixé par `v` (`v4.0.0`), sur `main`, une fois la CI verte.

4. Créer la release GitHub avec les fichiers de `storage/app/exports` en pièces jointes.

   ```bash
   gh release create v4.0.0 --generate-notes storage/app/exports/sql/dataset.sql
   ```

   Les notes sont générées à partir des Conventional Commits entre deux tags, d'où l'importance de leur format.

5. Publier les mêmes fichiers sur data.gouv.fr.

## Numérotation

Le numéro de version suit SemVer. Un changement du schéma des fichiers, par exemple la suppression d'une colonne, est un changement majeur.
