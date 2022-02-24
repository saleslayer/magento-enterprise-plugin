How to install:

-Uncompress module into magento2 root folder '/app/code'.

-From magento2 root folder execute commands:

	php bin/magento setup:upgrade
	php bin/magento setup:di:compile (if there's an error with 'var/di/' folder just delete it and execute this command again)
	php bin/magento setup:static-content:deploy
	php bin/magento cache:clean

-After executing the commands, Sales Layer module will be installed.

-Access to magento2 admin, and under Customers there is the Sales Layer section.
-Add a new connector introducing its credentials, then access the connector and synchronize it.
-Categories and products will be imported to the Root category Sales Layer, so the default Root category hast to be changed in the store. 
-Inside categories and products there will be two attributes; Sales Layer Product Identification and Sales Layer Product Company Identification, don't modify or delete this 	attributes or its values, otherwise, the products will be created again as new ones in the next synchronization.



Como instalarlo:

-Descomprimir el módulo en la carpeta raíz de magento2 '/app/code'.

-Desde la carpeta raíz de magento2 ejecutar los comandos:

    php bin/magento setup:upgrade
    php bin/magento setup:di:compile (si hay algún error con la carpeta 'var/di/', sólo hay que eliminarla y volver a ejecutar este comando)
    php bin/magento setup:static-content:deploy
    php bin/magento cache:clean

-Después de ejecutar los comandos, el módulo de Sales Layer estará instalado.

-Acceder al administrador de magento2, y debajo de Clientes estará la sección de Sales Layer.
-Añadir un nuevo conector introduciendo sus credenciales, entonces accediendo al conector y sincronizándolo.
-Las categorías y productos se importarán a la categoría raíz de Sales Layer, así que la categoría raíz por defecto ha de ser cambiada desde la tienda.
-Dentro de categorías y productos habrán dos atributos, Sales Layer Product Identification y Sales Layer Product Company Identification, no hay que modificar o eliminar estos atributos o sus valores, de lo contrario, los productos se crearían de nuevo en la siguiente sincronización.
