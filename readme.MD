#Profiler
Currently, only checking for string matches with `stripos` for speed.

##Header Checks
The JSON format for header checks is as follows:
```javascript
  "Drupal": {
    "x-generator": "Drupal",
    "x-drupal-cache": ""
  }
```
If you only need to check for the existence of the header key then leave the value blank
If you need to check both header and value, the script will check for the the entire header value for the substring that you put in the value

##Body Checks
```javascript
  "Google Tag Manager": [
    "googletagmanager.com",
    "googletagservices.com"
  ]
  ```
  The script will check for the string(s) in the entire HTML body. Try to be as explicit as possible
  
