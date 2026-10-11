# Catálogo de libros — Práctica Docker Compose

Sistema web distribuido en cinco contenedores para gestionar un catálogo de libros.
Permite crear, consultar, actualizar y eliminar libros, con caché en Redis,
notificaciones de prueba en Mailpit y persistencia en MariaDB.

## Arquitectura

| Servicio  | Tecnología         | Responsabilidad                        | Exposición         |
|-----------|--------------------|----------------------------------------|--------------------|
| frontend  | Nginx + HTML/JS    | Interfaz y proxy de /api/ hacia backend| Puerto host 8081   |
| backend   | PHP 8.3 + Apache   | API REST, MariaDB, Redis, SMTP         | Solo red interna   |
| db        | MariaDB 11         | Persistencia de libros                 | Solo red interna   |
| redis     | Redis 7 Alpine     | Caché de consultas y contador          | Solo red interna   |
| mailpit   | Mailpit v1.31      | Captura de correos de prueba           | Interfaz web 8025  |

## Requisitos

- Docker Desktop o Docker Engine con Compose v2
- Git

## Cómo ejecutar

1. Clonar el repositorio
   git clone <url> && cd proyecto-OROZCO-ADRIAN

2. Copiar el archivo de variables y completar las contraseñas
   cp .env.example .env

3. Construir y levantar todos los servicios
   docker compose up -d --build

4. Esperar a que db y redis estén healthy
   docker compose ps

## URLs de acceso

| Servicio     | URL                              |
|--------------|----------------------------------|
| Frontend     | http://localhost:8081            |
| Mailpit      | http://localhost:8025            |
| Health check | http://localhost:8081/api/health |
| Exportar     | http://localhost:8081/api/libros/export |

## Comandos de evidencia

docker compose config
docker compose ps
docker compose images
docker compose exec db mariadb -u admin -p Libros -e "SELECT * FROM libros;"
docker compose exec redis redis-cli KEYS "*"
docker compose exec redis redis-cli GET contador_consultas
docker network inspect proyecto-orozco-adrian_app_network
docker volume inspect proyecto-orozco-adrian_mariadb_data

## Notas técnicas

- MariaDB usa el puerto 3306, no 5432 como indica el documento original.
  El documento fue adaptado de una versión con PostgreSQL.
- Los nombres db, redis, mailpit y backend se resuelven por el DNS interno
  de Docker (127.0.0.11 dentro de cada contenedor). Desde la máquina host
  esos nombres no existen porque el DNS del sistema operativo no los conoce.
- Mailpit captura los correos pero no los entrega. Es una herramienta
  exclusiva de desarrollo.
- El archivo .env no está versionado. Usar .env.example como plantilla.

## Mejora individual: Exportación JSON

Se implementó un endpoint GET /api/libros/export en el backend PHP que
consulta todos los libros directamente desde MariaDB, sin caché, y devuelve
un archivo JSON descargable con formato legible y tildes correctas.
El frontend ofrece un botón "Exportar JSON" que activa la descarga.