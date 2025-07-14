# Auto Page Class Annotations

**A [ProcessWire](https://processwire.com/) module that automatically generates PHPDoc annotations for custom page
classes, enhancing IDE support and developer productivity.**

---

## What Does This Module Do?

This module automatically creates and maintains PHPDoc annotation blocks in your ProcessWire page class files. Every
time you modify fields or templates in the ProcessWire admin, the module intelligently updates the corresponding page
class files with accurate type information.

### Key Features

- **🔄 Automatic Updates**: Annotations update instantly when you modify fields or templates in the admin
- **🆕 Auto File Creation**: Creates page class files automatically if they don't exist
- **🛡️ Safe Overwriting**: Only replaces annotation blocks, preserving your custom code
- **🔧 Smart Type Detection**: Maps ProcessWire fieldtypes to appropriate PHP types
- **📁 Complete Coverage**: Supports regular templates, repeater fields, and repeater matrix fields
- **🏗️ RockPageBuilder Support**: Full integration with RockPageBuilder Block classes
- **💡 IDE Optimized**: Places annotations immediately before class declarations for perfect IDE compatibility
- **🔒 Duplicate Prevention**: Robust regex patterns prevent duplicate annotation blocks
- **⚙️ Flexible Configuration**: Optional strict types support and namespace handling

## IDE Benefits & Developer Experience

### Enhanced IntelliSense & Autocomplete

With proper PHPDoc annotations, your IDE can provide:

- **Field completion**: Type `$page->` and see all available fields with descriptions
- **Type checking**: Catch type-related errors before runtime
- **Return type hints**: Know exactly what each field returns (`string`, `PageArray`, `Pageimage`, etc.)
- **Parameter validation**: IDE warnings for incorrect field usage

### Before vs After Comparison

**Without annotations:**

```php
// IDE doesn't know what fields are available
$event = pages()->get('/events/jazz-night/');
$event->title; // No autocomplete, no type information
$event->musicians; // Could be anything - string? array? objects?
```

**With Auto Page Class Annotations:**

```php
// Tell your IDE which page class this variable represents
/** @var EventPage $event */
$event = pages()->get('/events/jazz-night/');
$event->title; // ✅ IDE knows this is a string
$event->musicians; // ✅ IDE knows this is PageArray
$event->images->first(); // ✅ IDE knows this returns Pageimage|null
```

> **Why the `@var` hint?** Since `pages()->get()` returns a generic `Page` object, your IDE doesn't know it's
> specifically an `EventPage` with custom fields. The `@var EventPage $event` line tells your IDE "treat this variable
> as
> an EventPage", enabling it to use the auto-generated annotations for autocomplete and type checking.

## Generated Output Example

The module creates annotation blocks like this:

```php
<?php namespace ProcessWire;

/** @AutoPageClassAnnotations
 * 
 * Template: event (Event Page)
 * @property string $title Title
 * @property string $summary Event Summary  
 * @property PageArray $musicians Musicians
 * @property Pageimages $images Event Images
 * @property int $datetime Event Date & Time
 * @property string $venue Venue Name
 *
 * @AutoPageClassAnnotations */

class EventPage extends Page {
    // Your custom methods here - they won't be touched!
    
    public function getFormattedDate(): string {
        return date('F j, Y', $this->datetime);
    }
}
```

## Supported Field Types

| ProcessWire Fieldtype | PHP Type                      | Example                                   |
|-----------------------|-------------------------------|-------------------------------------------|
| `FieldtypeText`       | `string`                      | `@property string $title`                 |
| `FieldtypePage`       | `Page\|PageArray\|false`      | `@property PageArray $categories`         |
| `FieldtypeImage`      | `Pageimages\|Pageimage\|null` | `@property Pageimages $gallery`           |
| `FieldtypeFile`       | `Pagefiles\|Pagefile\|null`   | `@property Pagefiles $downloads`          |
| `FieldtypeInteger`    | `int`                         | `@property int $sort_order`               |
| `FieldtypeCheckbox`   | `int`                         | `@property int $featured`                 |
| `FieldtypeRepeater`   | `RepeaterPageArray`           | `@property RepeaterPageArray $slides`     |
| `FieldtypeOptions`    | `SelectableOptionArray`       | `@property SelectableOptionArray $status` |

## RockPageBuilder Integration

The module provides seamless support for *
*[RockPageBuilder](https://www.baumrock.com/en/processwire/modules/rockpagebuilder/) Block classes**:

### Automatic Detection

- Detects templates starting with `rockpagebuilderblock-`
- Updates real Block implementations, not stub files

### Real File Locations

```
Template: rockpagebuilderblock-events
↓
Updates: /site/templates/RockPageBuilder/blocks/Events/Events.php
↓
Class: class Events extends Block
```

### Example RockPageBuilder Block

```php
<?php namespace RockPageBuilderBlock;

use RockPageBuilder\Block;

/** @AutoPageClassAnnotations
 * Template: rockpagebuilderblock-events
 * @property string $title Title
 * @property PageArray $events Linked Events
 * @AutoPageClassAnnotations */

class Events extends Block {
    // Your Block methods - fully preserved!
    
    public function getLabel() {
        return $this->events->implode(', ', 'title');
    }
}
```

## File Structure Support

- ✅ **ProcessWire Pages**: `<?php namespace ProcessWire;` → `/site/classes/`
- ✅ **Strict Types**: `<?php declare(strict_types=1); namespace ProcessWire;`
- ✅ **RockPageBuilder Blocks**: `<?php namespace RockPageBuilderBlock;` → `/site/templates/RockPageBuilder/blocks/`
- ✅ **Mixed formats**: Any combination of the above

## Installation & Configuration

1. Clone the module files from `https://github.com/robertweiss/AutoPageClassAnnotations` to
   `/site/modules/AutoPageClassAnnotations/`
2. Install the module in ProcessWire admin
3. **Optional**: Add to `/site/config.php` for strict types:
   ```php
   $config->AutoPageClassAnnotationsStrictPreamble = true;
   ```

The module will immediately start generating annotations for all existing templates.

## Credits

Heavily inspired by Robin Sallis' [Auto Template Stubs](https://github.com/Toutouwai/AutoTemplateStubs). Thx!
