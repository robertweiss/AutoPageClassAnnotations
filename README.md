# Auto Page Class Annotations
#### [Processwire](https://processwire.com/) module to automatically add a PHPDoc Block with annotations to custom page class files
---
This module adds an autogenerated PHPDoc block to every page class file inside /site/classes. It gets triggered on every field or template change inside the CMS. If the class file does not exist, it will be created first. The created block looks like this:

```php
<?php namespace ProcessWire;

/** @AutoPageClassAnnotations
 * 
 * Template: name_of_template
 * @property string $title Title
 * @property string $second_field Name of second Field
 *
 * @AutoPageClassAnnotations */

class NameOfTemplatePage extends Page {}
```

In existing page class files, only the segments between the start and end of @AutoPageClassAnnotations will be replaced. Files will be created/updated for templates and both repeater and repeater matrix fields.

Large segments of this module are borrowed from Robin Sallis' Auto Template Stubs, so credit to him! I needed something slightly different, so I adapted it to suit my requirements.

> [!WARNING]  
> This module has not been thoroughly tested and is only used in two of my personal projects at the moment. I recommend making backups before installing it and checking for any unwanted overwrites within your page class files!