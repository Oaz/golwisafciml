# PHP Classic WebServerOnly Shell

Imperative shell implementation for Conway's Game of Life (Version 1 - Monochrome) running on a web server with HTML/CSS output.

## Features

- Complete v1 imperative shell implementation
- HTML/CSS only output (NO JavaScript)
- Clocked mode via HTML `<meta http-equiv="refresh">` tag
- Clickable grid using `<a>` tags for cell selection
- State persistence via PHP sessions
- File-based pattern storage (JSON)
- Pattern catalog with 19 patterns from Appendix A
- Demo scene seeding with 13 stamps from Appendix C

## Requirements

- PHP 8.1+
- Composer (for dependency management)
- Any PHP-capable web server (Apache, Nginx, PHP-FPM, or PHP built-in server)

## Installation

```bash
cd php/classic/WebServerOnly
composer install
```

## Usage

### Using PHP Built-in Server

```bash
cd public
php -S localhost:8000
# Access at http://localhost:8000
```

### Using Apache/Nginx

Configure your web server to point to the `public/` directory.

## Architecture

The shell follows the Functional Core / Imperative Shell pattern:

- **Core**: Pure functional implementation (from `../core/`)
- **Shell**: Imperative implementation with ports for:
  - Display (HTML/CSS renderer)
  - PatternStore (file-based JSON storage)
  - State (PHP session-based)
  - EventSource (HTTP GET parameters)
- **Interpreter**: Executes programs returned by the core
- **RequestHandler**: Main entry point for HTTP requests

## File Structure

```
WebServerOnly/
├── composer.json                 # Composer configuration
├── composer.lock
├── bootstrap.php                 # Bootstrap with autoloading and session
├── config.php                   # Configuration constants
├── index.php                    # Entry point
├── README.md                    # This file
├── public/
│   └── index.php -> ../index.php (symlink)
├── patterns/                    # Pattern catalog (auto-generated)
│   ├── acorn.json
│   ├── beacon.json
│   └── ... (19 patterns)
└── src/
    ├── Models/
    │   └── ShellConfig.php       # Shell configuration
    └── Shell/
        ├── Interpreter.php      # Program interpreter
        ├── RequestHandler.php   # Request handler
        └── Ports/
            ├── DisplayPort.php   # HTML/CSS display
            ├── EventSourcePort.php # GET parameter parsing
            ├── PatternStorePort.php # File-based pattern storage
            ├── Port.php           # Port interface
            └── StatePort.php     # Session-based state
```

## Actions

| Action | Description | URL Parameters |
|--------|-------------|----------------|
| (none) | Initial load / display | - |
| `tick` | Advance one generation | `action=tick` |
| `stamp` | Stamp pattern at position | `action=stamp&pattern=NAME&x=X&y=Y` |
| `select_pattern` | Select pattern from dropdown | `action=select_pattern&pattern=NAME` |
| `toggle_status` | Show/hide status message | `action=toggle_status` |
| `clock_on` | Enable auto-ticking | `action=clock_on` |
| `clock_off` | Disable auto-ticking | `action=clock_off` |

## User Interface

- **Pattern Selector**: Dropdown with 19 catalog patterns
- **Clickable Grid**: Each cell is an `<a>` tag that stamps the selected pattern
- **Controls**: Step, Toggle Status, Clock On/Off
- **Status Bar**: Shows cell count when status is visible
- **Clock Mode**: Auto-refreshes every 0.1 seconds when enabled

## Testing

Run core tests:
```bash
cd ../core
./vendor/bin/phpunit tests/
```

## Deployment Notes

- PHP sessions must be configured (default file-based is fine)
- For Nginx/PHP-FPM, ensure `cgi.fix_pathinfo = 1` in php.ini
- No database required
- No JavaScript required

## License

AGPL-3.0
