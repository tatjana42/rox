.PHONY: all build phpcpd phploc phpmd php-cs-fixer php-code-sniffer phpmetrics phpunit version

SRC_DIR=src tests/TranslationLoader

null  :=
SPACE := $(null) $(null)
COMMA := ,
SRC_DIR_COMMA := $(subst $(SPACE),$(COMMA),$(SRC_DIR))

all: phpci

phpci: phpcpd phploc phpmd php-code-sniffer phpunit version

phpcsfix:
	./vendor/bin/phpcbf src
	./vendor/bin/php-cs-fixer fix -v

build:
	echo "`date`"
	./node_modules/.bin/encore dev
	php bin/console assets:install
	echo "`date`"

phpdox: phploc phpmd php-code-sniffer phpunit
	./vendor/bin/phpdox

mkdocs:
	mkdocs build

phpcpd:
	./vendor/bin/phpcpd $(SRC_DIR) --progress --no-interaction --exclude=Entity --exclude=Repository

phploc:
	./vendor/bin/phploc --log-xml=phploc.xml $(SRC_DIR)

phpmd:
	./vendor/bin/phpmd $(SRC_DIR_COMMA) text phpmd.xml

php-cs-fixer:
	./vendor/bin/php-cs-fixer fix -v --diff --dry-run --warning-severity=0

php-code-sniffer:
	./vendor/bin/phpcs  --colors --warning-severity=Error

phpunit:
	./vendor/bin/phpunit

phpmetrics:
	./vendor/bin/phpmetrics --exclude=src/App/Entity --report-violations=phpmetrics.xml $(SRC_DIR_COMMA)

version:
	git rev-parse --short HEAD > VERSION

checkjs:
	./node_modules/.bin/grunt checkjs