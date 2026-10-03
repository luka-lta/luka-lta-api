dockerCompose := "docker compose -f docker-compose.development.yml"
containerRun  := dockerCompose + " run --rm php-fpm-api"

install:
    composer install

dev:
    {{dockerCompose}} up

stop:
    {{dockerCompose}} down

lint:
    {{containerRun}} vendor/bin/phpmd src text phpmd.xml
    {{containerRun}} vendor/bin/phpcs src

# Nur phpcs. phpmd laeuft unter PHP 8.4 nicht (pdepend-Inkompatibilitaet),
# deshalb bricht `just lint` vor phpcs ab.
lint-cs:
    {{containerRun}} vendor/bin/phpcs src

# phpcs auf einzelne Pfade. Das Gate fuer neuen Code: `src` enthaelt
# Altbestand mit Line-Length-Warnings, die hier nicht mitrauschen sollen.
lint-path +PATHS:
    {{containerRun}} vendor/bin/phpcs {{PATHS}}

build ENV="development":
    DOCKER_BUILDKIT=1 COMPOSE_DOCKER_CLI_BUILD=1 docker compose -f docker-compose.{{ENV}}.yml build --pull
