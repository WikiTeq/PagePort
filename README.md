PagePort
----------

The extension provides a maintenance script to export and import wiki pages in a git-book format:

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

Requirements:

* MediaWiki 1.30+
* php-zip extension (optional)

Exporting pages

```
# Export page from "Test" category, save to ~/export/ folder
php extensions/PagePort/maintenance/exportPages.php --category Test --out ~/export/
# Export page listen in pages.txt, save to ~/export/ folder
php extensions/PagePort/maintenance/exportPages.php --pagelist pages.txt --out ~/export/
# Export all the pages, save to ~/export/ folder
php extensions/PagePort/maintenance/exportPages.php --full Test --out ~/export/
# Export all the pages, save to ~/export/ folder, zip it and save the archive to ~/full.zip
php extensions/PagePort/maintenance/exportPages.php --full Test --out ~/export/ --zip ~/full.zip
```

Importing page

```
# Import pages from ~/export/ directory
php extensions/PagePort/maintenance/importPages.php --source ~/export/
# Import pages from ~/export/ directory, make edits on behalf of the Admin user
php extensions/PagePort/maintenance/importPages.php --source ~/export/ --user Admin
```

See `php extensions/PagePort/maintenance/importPages.php --help` for details
