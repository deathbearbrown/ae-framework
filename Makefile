.PHONY: test
test: test-style test-unit

.PHONY: test-unit
test-unit:
	cd test/ &&  ../vendor/bin/phpunit --debug

.PHONY: test-style
test-style:
	phpcs -p --standard=./ruleset.xml
