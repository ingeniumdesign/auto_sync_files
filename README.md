# auto_sync_files

TYPO3 8.7 Auto-Sync-Files Extension

This Extension Auto-Sync-Files downloads externals files periodically via Scheduler to your Webspace. Thus you do always have the newest version of a file locally stored. This can be really useful to improve performance because external files can be cached this way.
It also allows you to clear the cache everytime there is a new version of the file.


### Example with Google Analytics Code

#### Setup
* Go to Scheduler, add a new Task and chose "auto_file_sync".

* Set the frequency to any number you want (we recoment 3600 = one hour) and the description to something you can remember, e.g. "Google Analytics Downloader".

* Now set "The URL of the File that is supposed to be downloaded" to the destination of the Google Analytics-Script: https://www.google-analytics.com/analytics.js 

* In "The absolute path the file should be saved to" you fill in the local path where the Javascript-File should be saved to. E.g. "/html/typo3/fileadmin/Templates/Assets/JavaScript/analytics.js"

* If you want the frontend-cache to be cleared everytime there is a different version than the stored one, tik the checkbox "Clear Frontend-Cache after execution" (NOTICE: The cache is only cleared if there is a difference between the two files). 

* Include the locally-synced-file in your Typoscript with includeJS...
  * Example: includeJSFooter.googleAnalytics = fileadmin/ DOWNLOAD FILE PATH HERE

* Replace the default Google-Analytics-Code to one that does not load the external Library (you can place this bit in a Javascript-File of your choice). We just removed the part where it loads the external library from the original code by Google:
```javascript
// Google analytics like the original one
 (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
     (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();
     // This line is removed
 })(window,document,'script','','ga');
 ga('set', 'anonymizeIp', true);
 ga('create', 'UA-XXXXXX-1', 'auto'); // Your tracking-ID 
 ga('send', 'pageview');
 ```
 
You can use the Auto-Sync-Files Extension for the Google PageSpeed Tools Insights!

Browser-Caching Time Error:
https://www.google-analytics.com/analytics.js (2 Hour)

# Contact &amp; Communication

## GIT

We are on github:<br />
https://github.com/ingeniumdesign/auto_sync_files/


## Agency

INGENIUMDESIGN<br />
TYPO3 - Internetagentur<br />
In der Eisenbach 22<br />
65510 Idstein<br />
<br />
http://www.ingeniumdesign.de/<br />
info@ingeniumdesign.de

## Donate

Patreon: https://www.patreon.com/typo3probleme/<br />
BTC: 1Emte6AxnifWqt7N8vSqSF7JK1K6CYuBj4<br />
LTC: Lfs2F8DabYuunxYw2ym9CRLAMBKZUaaBNh<br />
ETH: 0x95298b41564f070bc83bc76159bb7804d26483d6<br />
PayPay: www.paypal.me/INGENIUMDESIGN/

## Used by

We are searching for LIVE-References or Live-Examples for the TYPO3 Auto-Sync-Files Extension.<br />
Please be so kind to send us an E-Mail if you're using it. Thanks!

**Links/References:**

https://www.easy-sprachreisen.de/ - by INGENIUMDESIGN<br />