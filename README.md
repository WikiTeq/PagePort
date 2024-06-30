PagePort
----------

[![CI](https://github.com/WikiTeq/PagePort/actions/workflows/mediawiki.yml/badge.svg)](https://github.com/WikiTeq/PagePort/actions/workflows/mediawiki.yml)

The extension provides a maintenance script to export and import wiki pages in a git-book format and JSON format:

```
/
- Main
-- Page1.mediawiki
-- Page2.mediawiki
- Category:
-- Category1.mediawiki
-- Category2.mediawiki
- Form:
-- Form1.mediawiki
-- Form2.mediawiki
...
```

```json
{
	"publisher": "Test",
	"author": [
		"Test"
	],
	"language": "en",
	"url": "https://github.com/Test/test",
	"packages": {
        "Books demo (SMW)": {
            "globalID": "com.test.test",
            "publisherURL": "https://test.com",
            "description": "Lorem",
            "version": "0.1",
            "pages": [
                {
                    "name": "Page1",
                    "namespace": "NS_MAIN",
                    "url": "https://raw.githubusercontent.com/Test/test/master/Main/Test"
                },
                ...
            ]
        }
    }
}
```

# Requirements:

* MediaWiki 1.30+
* php-zip extension (optional)

# Exporting pages

```bash
# Export page from "Test" category, save to ~/export/ folder
php maintenance/exportPages.php --category Test --out ~/export/

# Export page listed in pages.txt, save to ~/export/ folder
php maintenance/exportPages.php --pagelist pages.txt --out ~/export/

# Export all the pages, save to ~/export/ folder
php maintenance/exportPages.php --full Test --out ~/export/

# Export all the pages, save to ~/export/ folder, zip it and save the archive to ~/full.zip
php maintenance/exportPages.php --full Test --out ~/export/ --zip ~/full.zip
```

# Importing page

```bash
# Import pages from ~/export/ directory
php maintenance/importPages.php --source ~/export/

# Import pages from ~/export/ directory, make edits on behalf of the Admin user
php maintenance/importPages.php --source ~/export/ --user Admin
```

# PageExchange format

It's possible to export JSON instead of set of files, the generated json is compatible with the
PageExchange extension: https://www.mediawiki.org/wiki/Extension:Page_Exchange

```bash
# Export pages from "Test" category, save to ~/export/test.json file
php maintenance/exportPages.php --category Test --out ~/export/test.json --json

# Rewrite pages URLs to point them to a GitHub repository at "someone/Repo":
php maintenance/exportPages.php --category Test --out ~/export/test.json --json --github "someone/Repo"

# You can also omit the filename, in that case filename will be generated based on time():
php maintenance/exportPages.php --category Test --out ~/export/ --json

# It's also possible to specify package details:
php maintenance/exportPages.php --category Test --out ~/export/test.json --json /
    / --version 1.0 --package "MyPackage" --desc "My description"
```

See `php maintenance/importPages.php --help` for details

# Contribution

TODO
