{ pkgs, ... }:

{
  packages = [
    pkgs.php83
    pkgs.php83Packages.composer
    pkgs.typst
  ];

  languages.php = {
    enable = true;
    version = "8.3";
    extensions = [ "xdebug" "redis" "memcached" ];
    ini = ''
      memory_limit = 512M
      max_execution_time = 120
      upload_max_filesize = 32M
      post_max_size = 32M
      xdebug.mode = coverage
      xdebug.start_with_request = yes
    '';
  };

  scripts = {
    test.exec = "vendor/bin/phpunit";
    test-coverage.exec = "vendor/bin/phpunit --coverage-html coverage-html";
    test-unit.exec = "vendor/bin/phpunit tests/Unit";
    test-feature.exec = "vendor/bin/phpunit tests/Feature";
    install.exec = "composer install";
    update.exec = "composer update";
  };

  enterShell = ''
    echo "🎯 Laravel Typst Development Environment"
    echo "📦 PHP $(php --version | head -n1)"
    echo "📄 Typst $(typst --version)"
    echo "🎼 Composer $(composer --version | head -n1)"
    echo ""
    echo "Available commands:"
    echo "  install      - Install PHP dependencies"
    echo "  test         - Run all tests"
    echo "  test-unit    - Run unit tests only"
    echo "  test-feature - Run feature tests only"
    echo "  test-coverage- Generate coverage report"
    echo ""
    echo "Ready to develop! 🚀"
  '';

  processes = {
    # Add any background processes if needed
  };

  enterTest = ''
    vendor/bin/phpunit
  '';

  env = {
    TYPST_BIN_PATH = "typst";
    TYPST_TIMEOUT = "30";
    APP_ENV = "testing";
  };
}