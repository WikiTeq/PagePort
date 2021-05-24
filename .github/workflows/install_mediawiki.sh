#!/bin/bash

MW_BRANCH=$1
EXTENSION=$2

wget https://github.com/wikimedia/mediawiki/archive/$MW_BRANCH.tar.gz -nv

tar -zxf $MW_BRANCH.tar.gz
mv mediawiki-$MW_BRANCH mediawiki

cd mediawiki

sed -i 's/"merge-dev": false/"merge-dev": true/g' composer.json
composer install
php maintenance/install.php --dbtype sqlite --dbuser root --dbname mw --dbpath $(pwd) --pass AdminPassword WikiName AdminUser

# echo 'error_reporting(E_ALL| E_STRICT);' >> LocalSettings.php
# echo 'ini_set("display_errors", 1);' >> LocalSettings.php
echo '$wgShowExceptionDetails = true;' >> LocalSettings.php
echo '$wgShowDBErrorBacktrace = true;' >> LocalSettings.php
echo '$wgDevelopmentWarnings = true;' >> LocalSettings.php

echo "wfLoadExtension( '$EXTENSION' );" >> LocalSettings.php

ls -al extensions
cat <<EOT >> composer.local.json
{
	"extra": {
		"merge-plugin": {
			"merge-dev": true,
			"include": [
				"skins/*/composer.json",
				"extensions/*/composer.json"
			]
		}
	},
	"minimum-stability": "dev"
}
EOT
