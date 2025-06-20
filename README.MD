# Redirect Fixer Component
A Joomla! component for automatically scanning and updating internal article links based on your site's defined redirects.

Are you struggling with outdated or broken internal links in your Joomla! articles after migrating content or setting up redirects? The Redirect Fixer component scans your article content, identifies URLs that match your site's published redirects, and allows you to easily update them to the new, correct destinations, ensuring your content always points to the right place.

## How To Use Redirect Fixer Component
1.  **Install the component** via the Joomla! Extensions Manager. (You can typically install from a downloaded `.zip` file.)
2.  Navigate to **Components > Redirect Fixer** in the Joomla! administrator.
3.  Click **"Scan for Affected Articles"** to analyze your article content against your configured redirects.
4.  Select the specific links you wish to update and click **"Fix Selected Articles"** or **"Fix All Articles"** to apply the changes to your content.

## Features
* **Seamless Joomla! Redirects Integration**: Uses your existing redirect rules managed in Joomla!'s built-in Redirect Component.
* **Comprehensive Article Content Scan**: Scans both the introduction (`introtext`) and full article content (`fulltext`) of all published Joomla! articles for outdated URLs.
* **Intelligent URL Matching**: Handles various formats (relative/absolute paths) and allows for optional query string inclusion.
* **Secure Content Updates**: Respects article checkout/check-in status, and maintains database integrity.
* **Granular Control**: Provides a clear overview of detected links and their proposed new URLs, allowing you to review and choose which updates to apply.

## Requirements
This component works with Joomla! versions **4.0 or later** and requires **PHP 7.4 or later**.

## FAQ
**Q: Will this component fix redirects for external websites or third-party component URLs?**
**A:** No, the component specifically focuses on **internal links** within your Joomla! site's articles that correspond to redirects you've set up *for your own domain*. It will skip old redirect URLs that point to external domains.

**Q: Can it fix URLs in modules, custom HTML, or content fields outside of main articles?**
**A:** Currently, it's designed to scan and update URLs exclusively within the `introtext` and `fulltext` fields of Joomla! articles (`#__content` table). Other content areas are not covered.

**Q: This component is awesome! Can I send a donation?**  
**A:** Sure! Send your cryptonation to the following wallets:

`BTC 1PXWZJcBfehqgV25zWdVDS6RF2yVMxFkZD`

`Eth 0xC9b695D4712645Ba178B4316154621B284e2783D`

**Q: Got any more awesome Joomla! extensions?**  
**A:** Find them [right here](https://naftee.com)
