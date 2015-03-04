# MyBB Plugin: XEM Fast Reputation

## DESCRIPTION

**XEM Fast Reputation** is a plugin familiar to users of IPB, allows rapid evaluation of posts. This extension I use the built in MyBB reputation, so it quick and does not burden the server. Performs only one additional SQL query to retrieve and process information about liked posts. Evaluate the posts can be positive (+1) and negative (-1). We can always change your assessment of the post or cancel it completely.

### This plugin requires PHP version 5.4 or higher!

## INSTALATION

1. Upload files
  * upload files from "upload" to your MyBB root directory

2. {$post['xem_fast_rep']}
  * add variable {$post['xem_fast_rep']} into Your Template → Post Bit Templates → postbit and postbit_classic before {$post['attachments']}

3. JS
  * add into Your Template → Ungrouped Templates → headerinclude before {$stylesheets}:
  `<script type="text/javascript" src="{$mybb->asset_url}/jscripts/xem_fast_rep.js"></script>`

4. CSS
  * add CSS into global.css from css.txt file

5. Install & activate
  * Install and activate the plugin