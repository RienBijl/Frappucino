<?php

namespace Kernel\Console\Commands\Brew;

use Kernel\Console\Command;

class Provider extends Command {

    /**
     * @inheritDoc
     */
    public function run(array $args) : void
    {
        if(!isset($args[2]))
        {
            parent::error("You did not properly specify a provider-name");
        } else {
            $provider = $args[2];
            $path = __DIR__ . '/../../../../app/Providers/' .$provider .'.php';
            if(file_exists($path))
            {
                parent::error("This provider already exists");
            } else {
                $file = fopen($path, "w") or parent::warn("Cannot generate provider, try chmod") and exit;
                fwrite($file, $this->genContent($provider)) or parent::warn("Could not supply content, try chmod") and exit;
                fclose($file) or parent::warn("Could not close file link") and exit;
                parent::msg("Successfully made " .$provider . ' at ' . $path);
            }
        }
    }

    /**
     * Generate the content for the file
     * @param string $name
     * @return string
     */
    private function genContent(string $name) : string
    {
        return <<<EOT
<?php

namespace App\Providers;

class $name {

    private function register()
    {
        // Register your data
    }

    static \$boot = false;

    public static function boot()
    {
        if(!self::\$boot)
        {
            self::\$boot = true;
            (new self())->register();
        }
    }

}

EOT;
    }

    /**
     * @inheritDoc
     */
    public static function invoke(array $args) : void
    {
        (new self)->run($args);
    }
}