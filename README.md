# Laravel Typst

A Laravel package that provides a convenient facade for compiling Typst documents directly from your Laravel application.

## Installation

First, add the repository to your Laravel app's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/durableprogramming/durable-laravel-typst"
        }
    ]
}
```

Then install the package via composer:

```bash
composer require durableprogramming/durable-laravel-typst
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=typst-config
```

This will create a `config/typst.php` configuration file where you can customize the package settings:

```php
return [
    'bin_path' => env('TYPST_BIN_PATH', 'typst'),
    'working_directory' => env('TYPST_WORKING_DIR', storage_path('typst')),
    'timeout' => env('TYPST_TIMEOUT', 60),
    'format' => env('TYPST_FORMAT', 'pdf'),
    'font_paths' => [],
    'root' => env('TYPST_ROOT', null),
];
```

### Configuration Options

- **bin_path**: Path to the Typst binary (default: 'typst')
- **working_directory**: Directory for temporary files (default: storage/typst)
- **timeout**: Maximum compilation time in seconds (default: 60)
- **format**: Default output format (default: 'pdf')
- **font_paths**: Additional font directories for Typst
- **root**: Default root directory for compilation

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or 11.0
- Typst binary installed on your system

## Usage

### Basic Usage

```php
use Typst;

// Compile Typst source to PDF
$outputPath = Typst::compile('#set text(font: "Arial")
Hello, World!');

// Get compiled content as string
$pdfContent = Typst::compileToString('#set text(font: "Arial")
Hello, World!');

// Compile from file
$outputPath = Typst::compileFile('/path/to/document.typ');
```

### Advanced Usage

```php
// Compile with custom options
$outputPath = Typst::compile($source, [
    'format' => 'png',
    'root' => '/custom/root/path',
    'font_paths' => ['/path/to/fonts']
]);

// Compile file with custom output path
$outputPath = Typst::compileFile(
    '/path/to/input.typ',
    '/path/to/output.pdf',
    ['format' => 'pdf']
);

// Runtime configuration
Typst::setConfig([
    'timeout' => 120,
    'format' => 'svg'
]);
```

### Using Dependency Injection

```php
use Durable\LaravelTypst\TypstService;

class DocumentController extends Controller
{
    public function __construct(
        private TypstService $typst
    ) {}

    public function generateReport()
    {
        $content = $this->typst->compileToString('
            #set page(paper: "a4")
            = Monthly Report
            
            This is the report content.
        ');

        return response($content)
            ->header('Content-Type', 'application/pdf');
    }
}
```

## Error Handling

The package throws `TypstCompilationException` when compilation fails:

```php
use Durable\LaravelTypst\Exceptions\TypstCompilationException;

try {
    $output = Typst::compile($source);
} catch (TypstCompilationException $e) {
    $errorMessage = $e->getMessage();
    $exitCode = $e->getExitCode();
    // Handle compilation error
}
```

## Available Methods

### TypstService Methods

- `compile(string $source, array $options = []): string` - Compile Typst source and return output file path
- `compileToString(string $source, array $options = []): string` - Compile and return content as string
- `compileFile(string $inputPath, string $outputPath = null, array $options = []): string` - Compile from file
- `setConfig(array $config): self` - Update configuration at runtime
- `getConfig(): array` - Get current configuration

### Supported Options

- `format`: Output format (pdf, png, svg, etc.)
- `root`: Root directory for includes and imports
- `font_paths`: Array of additional font directories

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

