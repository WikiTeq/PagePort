#!/bin/bash

MW_BRANCH=$1
EXTENSION=$2

echo "Installing MediaWiki $MW_BRANCH with extension $EXTENSION .."

# Download mediawiki branch
wget https://github.com/wikimedia/mediawiki/archive/$MW_BRANCH.tar.gz -nv

# Extract
tar -zxf $MW_BRANCH.tar.gz
mv mediawiki-$MW_BRANCH mediawiki

# Navigate to the wiki dir
cd mediawiki

# Enable merge for dev dependencies on composer-merge plugin
sed -i 's/"merge-dev": false/"merge-dev": true/g' composer.json

# Install vendor dependencies
composer install --no-progress

# Initialize the database
php maintenance/install.php --dbtype sqlite --dbuser root --dbname mw --dbpath $(pwd) --pass AdminPassword WikiName AdminUser

# Enable errors output
# echo 'error_reporting(E_ALL| E_STRICT);' >> LocalSettings.php
# echo 'ini_set("display_errors", 1);' >> LocalSettings.php
echo '$wgShowExceptionDetails = true;' >> LocalSettings.php
echo '$wgShowDBErrorBacktrace = true;' >> LocalSettings.php
echo '$wgDevelopmentWarnings = true;' >> LocalSettings.php

# Load the extension
echo "wfLoadExtension( '$EXTENSION' );" >> LocalSettings.php

# Load all the skins and extensions packages
cat <<EOT > composer.local.json
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
