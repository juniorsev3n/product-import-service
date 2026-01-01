
# Product CSV Import Service (Laravel 10)

  

## Fitur

  

- Import CSV async

- Chunk processing

- Status tracking

- Partial failure

- Stress test 100K rows

  

## Instalasi

  

```bash

composer  install

cp  .env.example  .env

php  artisan  key:generate

```

  

## Konfigurasi

  

- DB: PostgreSQL

```

DB_CONNECTION=pgsql

DB_HOST=127.0.0.1

DB_PORT=5432

DB_DATABASE=product-service

DB_USERNAME=postgres

DB_PASSWORD=

```

- Queue: Redis

```

QUEUE_CONNECTION=redis

REDIS_CLIENT=predis

REDIS_HOST=

REDIS_PASSWORD=

REDIS_PORT=

```

- Import Konfigurasi: config/import.php

```

<?php

return [

'chunk_size' => 100,

'queue' => 'imports',

'max_file_size' => 51200, // 50 MB

'allowed_extensions' => ['csv'],

];

```

  

## Jalankan Migrasi dan Service Worker

  

```bash

## migrasi database

php  artisan  migrate

## lalu

php  artisan  queue:work  --queue=default,imports

```

## Create User & Token

```

php artisan user:create admin@local.service

```

Simpan token untuk Authentication API

  
  

## Endpoint

  

POST /api/import/products

```

curl --location '/api/import/products' \

--header 'Authorization: Bearer {{token}}\

--form 'file=@"products_100k_stress_test.csv"'

```

  

GET /api/import/status/{id}

```

curl --location '/api/import/status/{{id}}' \

--header 'Authorization: Bearer {{token}}'

```

  
  

## Postman Collection

[Link Here](https://.postman.co/workspace/My-Workspace~1b800e3d-d9da-45f8-86da-6a87952b2630/collection/2330963-4ba62637-39e6-4015-9765-6f59edaee1b7?action=share&creator=2330963&active-environment=2330963-e682347e-cd95-4b86-bbb4-f460f81b5c14)

  

## CSV Format

  

sku,name,price,stock

  
  

## Logging Monitoring

```

tail -f storage/logs/import.log

```

  

## Author

  

Irfan Maulana