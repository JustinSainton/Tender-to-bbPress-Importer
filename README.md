Tender-to-bbPress-Importer
==========================

Uses Tender's neat JSON API to import discussions.  Eventually, would like the API to support importing everything from the endpoints provided by Tender.  For my specific use case, all that is needed is discussions.

Set-up is pretty easy.  Just define the following in wp-config.php.  No settings, UI or anything. 
We'll automatically start the migration and keep you posted of progress via an admin notice.

define( 'TENDER_API_TOKEN', 'token is found in your profile/edit area at Tender' );
define( 'TENDER_API_BASE', 'https://api.tenderapp.com/your-site' );