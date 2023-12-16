# tasks


`composer require controleonline/tasks:dev-master`



Create a new fila on controllers:
config\routes\controllers\tasks.yaml

```yaml
controllers:
    resource: ../../vendor/controleonline/tasks/src/Controller/
    type: annotation      
```

Add to entities:
nelsys-api\config\packages\doctrine.yaml
```yaml
doctrine:
    orm:
        mappings:
           tasks:
                is_bundle: false
                type: annotation
                dir: "%kernel.project_dir%/vendor/controleonline/tasks/src/Entity"
                prefix: 'ControleOnline\Entity'
                alias: ControleOnline                             
```          


Add this line on your routes:
config\packages\api_platform.yaml
```yaml          
mapping   :
    paths: ['%kernel.project_dir%/src/Entity','%kernel.project_dir%/src/Resource',"%kernel.project_dir%/vendor/controleonline/tasks/src/Entity"]        
```          
