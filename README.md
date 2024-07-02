# Silverstripe Image Credits
Module for SilverStripe 5 adding credits to the image model. 

## Development / Testing
During development or settings tests, the default caching behaviour is a pain - so you can disable it with a custom config like the following:

```yaml
---
Name: custom-image-credits
After: level51-image-credits
---
Level51\ImageCredits\ImageExtension:
  force_rebuild: true
```

## Requirements
- SilverStripe ^5.0
- PHP >= 8.0

## Maintainer
- Level51 <hallo@lvl51.de>
