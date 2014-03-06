Tender-to-bbPress-Importer
==========================

Uses Tender's neat JSON API to import discussions.  Eventually, would like the API to support importing everything from the endpoints provided by Tender.  For my specific use case, all that is needed is discussions.

Set-up is pretty easy.  Just define the following in wp-config.php.  No settings, UI or anything. 

`define( 'TENDER_API_TOKEN', 'token is found in your profile/edit area at Tender' );`
`define( 'TENDER_API_BASE', 'https://api.tenderapp.com/your-site' );`

We'll automatically start the migration and keep you posted of progress via an admin notice.  To run the import again from the last topic created, simply visit your-site.com/wp-admin/?re-run-importer=true

To help with some of the concepts - "Categories" are Forums, "Discussions" are Topics and "Comments" are Replies.

@todo - Would be neat on activation to grab categories and offer some mapping options.  Not going to do that this round.

Supports the bbPress Private Replies and bbPress Marked as Resolved add-ons for bbPress.