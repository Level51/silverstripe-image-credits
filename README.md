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

## Image Credits Page
There is `$ImageWithCredits` variable globally available in all templates which can be used to render a list of all images with its credits. You can therefore use it in any template, e.g. like this:

```
<% loop $ImageWithCredits %>
    <div style="display: flex;">
        <div style="flex: none; margin-right: 2rem;">
            $ScaleWidth(200)
        </div>

        <div style="flex: auto;">
            $Credits
        </div>
    </div>
<% end_loop %>
```

## Requirements
- SilverStripe ^5.0
- PHP >= 8.0

## Maintainer
- Level51 <hallo@lvl51.de>
