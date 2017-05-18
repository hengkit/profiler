# Profiler

1. Checks site features via header and body heuristics
2. Checks Security using [SSL Labs API](https://www.ssllabs.com/projects/ssllabs-apis/)
3. Checks performance using [Browsertime](https://github.com/sitespeedio/browsertime)
4. Writes results to Google Docs (setup required)

## Installation

1. Install PHP (I use homebrew and 5.6, anything should work)
2. Install [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx)
3. Install [Node.js](https://nodejs.org/en/download/)
4. Install Browsertime
```bash
npm install -g browsertime
```
5. Clone this repo.
6. Install Dependencies (Google Docs Client)
```bash
composer install
```
7. Get the google credentials file from a yet to be determined source.
8. Run the script.
```bash
php cli.php https://samplesite.com
```

## Feature Checks
Feature checks may be added to the JSON files.
### Header Checks
The JSON format for header checks is as follows:
```javascript
  "Drupal": {
    "x-generator": "Drupal",
    "x-drupal-cache": ""
  }
```
* If you only need to check for the existence of the header key then leave the value blank
* If you need to check both header and value, the script will check for the the entire header value for the regex (without leading and trailing slashes) that you put in the value

### Body Checks
```javascript
  "Google Tag Manager": [
    "googletagmanager.com",
    "googletagservices.com"
  ]
  ```
  The script will check for the string(s) in the entire HTML body. Try to be as explicit as possible

## Security Checks
 SSL Labs PHP library via [Björn Roland](https://github.com/bjoernr-de/php-ssllabs-api)

### Scores
The letter grade that is assigned by SSL Labs is a pretty involved [formula](https://github.com/ssllabs/research/wiki/SSL-Server-Rating-Guide)
#### tl;dr
* If your site is susceptible to any known HTTPS attacks, weak ciphers or vulnerabilities, it is capped at a B but most likely will score lower.
* An A+ is achievable on Pantheon using our free HTTPS service AND setting up [HSTS](https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security).
* An A is the default score for Pantheon sites using the free HTTPS service.

## Performance Check
Performance Checks are done using browsertime. This must be installed locally using `npm`.
```bash
npm install -g browsertime
```
### Backend Time
The time it takes the server to get the first byte back to the browser
### First Paint Time
The time it takes for the user sees something happening in the browser
### DOM Content Loaded Time
The time it takes for the entire page (HTML, CSS, JS) to be completely loaded in the browser.
### Page Load Time
The time it takes for the page to be completely rendered in the browser.
### RUM Speed Index

Here's a very long [deck](https://www.slideshare.net/ddprrt/speed-index-explained) that explains Real User Monitoring (RUM) Speed Index

#### tl;dr

We use a real browser to calculate your speed index. It's based on what your browser times the page as visually complete as. Scores under 3000 are good.

## TODO
1. Stop using separate JSON files
2. So much error checking
3. Webify
4. Figure out how to multithread browsertime without it nerfing
