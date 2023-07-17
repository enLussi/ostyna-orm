# Framework Ostyna : ORM

## 1. Création des entités

Les données des entitées sont stockées dans le fichier entities.json du dossier migrations. Pour simplifier la tâches de l'utilisateur, il est possible de généré 
automatiquement ce fichier avec la commande
```sh
php bin/console table --new
```
Des informations vous seront demandé concernant la table et les propriétés la concernant.

Il est possible de supprimer ou ajouter des propriétés après l'exécution de la première commande
```sh
php bin/console table --modify
```

Pour supprimer une table entière:
```sh
php bin/console table --remove
```

Une fois toutes vos tables créer, il est possible de générer le code sql permettant d'appliquer les modifications sur la base de données.
```sh
php bin/console table --prepare
```
Cette commande générera un fichier de classe final héritant de ***Ostyna\ORM\Migrations\AbstractMigrations*** dans le dossier migrations avec une méthode upgrade contenant le code sql nécessaire pour
appliquer les changements basé sur le fichier entities.json du dossier migrations.

Pour envoyer ces changements à la base de données:
```sh
php bin/console table --migrate
```

Il est possible de générer les classes d'entité de chaque table une par une.
```sh
php bin/console table --generate=<ClasseName>
```

## 2. DataSupplies

Les tableaux générés dans la base de données sont vides. Ils peuvent être rempli via une classe "Supply".
```sh
php bin/console supply --unique=<EntityName>
```
Il faut utiliser le nom de l'entité comme vous l'avez défini lors de sa création.

```
