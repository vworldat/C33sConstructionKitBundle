<?php

/*
 * Back-ported from PR https://github.com/sensiolabs/SensioGeneratorBundle/pull/260
 *
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace C33s\ConstructionKitBundle\Manipulator;

use Sensio\Bundle\GeneratorBundle\Manipulator\Manipulator;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Changes the PHP code of a Kernel.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class KernelManipulator extends Manipulator
{
    protected $kernel;
    protected $reflected;

    /**
     * Constructor.
     *
     * @param KernelInterface $kernel A KernelInterface instance
     */
    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        $this->reflected = new \ReflectionObject($kernel);
    }

    /**
     * Writes new bundle(s) to AppKernel.
     *
     * @param array $lines Lines to write
     *
     * @return bool true if write was successful, false otherwise.
     */
    private function writeAppKernel($src, $bundles)
    {
        $method = $this->reflected->getMethod('registerBundles');
        $lines = array_slice($src, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);

        $this->setCode(token_get_all('<?php '.implode('', $lines)), $method->getStartLine());
        while ($token = $this->next()) {
            // $bundles
            if (T_VARIABLE !== $token[0] || '$bundles' !== $token[1]) {
                continue;
            }

            // =
            $this->next();

            // array
            $token = $this->next();
            if (T_ARRAY !== $token[0]) {
                return false;
            }

            // add the bundle at the end of the array
            while ($token = $this->next()) {
                // look for );
                if (')' !== $this->value($token)) {
                    continue;
                }

                if (';' !== $this->value($this->peek())) {
                    continue;
                }

                // we have reached ";"
                $this->next();

                $lines = array_merge(
                    array_slice($src, 0, $this->line - 2),
                    // Appends a separator comma to the current last position of the array
                    array(rtrim(rtrim($src[$this->line - 2]), ',').",\n"),
                    array($bundles),
                    array_slice($src, $this->line - 1)
                );

                file_put_contents($this->reflected->getFilename(), implode('', $lines));

                return true;
            }
        }
    }

    /**
     * isBundleDefined.
     *
     * @param array  $src    ource file to check
     * @param string $bundle Bundle to check for
     *
     * @return bool Is the bundle already defined?
     */
    private function isBundleDefined($src, $bundle)
    {
        $method = $this->reflected->getMethod('registerBundles');
        $lines = array_slice($src, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1);

        return (bool) false !== strpos(implode('', $lines), $bundle);
    }

    /**
     * Adds a bundle at the end of the existing ones.
     *
     * @param string $bundle The bundle class name
     *
     * @return bool true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already defined
     */
    public function addBundle($bundle)
    {
        if (!$this->reflected->getFilename()) {
            return false;
        }

        $src = file($this->reflected->getFilename());

        // Don't add same bundle twice
        if ($this->isBundleDefined($src, $bundle)) {
            throw new \RuntimeException(sprintf('Bundle "%s" is already defined in "AppKernel::registerBundles()".', $bundle));
        }

        $bundleLoader = sprintf("            new %s(),\n", $bundle);

        return $this->writeAppKernel($src, $bundleLoader);
    }

    /**
     * Adds multiple bundles at the end of the existing ones.
     *
     * @param array $bundle The bundle class names to add
     *
     * @return bool true if it worked, false otherwise
     *
     * @throws \RuntimeException If bundle is already defined
     */
    public function addBundles(array $bundles = array())
    {
        if (!$this->reflected->getFilename()) {
            return false;
        }

        $src = file($this->reflected->getFilename());

        $bundleLoader = '';

        // Don't add same bundle twice
        foreach ($bundles as $bundle) {
            if (!$this->isBundleDefined($src, $bundle)) {
                $bundleLoader .= sprintf("            new %s(),\n", $bundle);
            }
        }

        if ('' == $bundleLoader) {
            throw new \RuntimeException('All bundles already defined in "AppKernel::registerBundles()".');
        }

        return $this->writeAppKernel($src, $bundleLoader);
    }
}
