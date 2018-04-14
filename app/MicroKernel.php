<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\TwigBundle\TwigBundle;

/**
 * Class MicroKernel
 */
class MicroKernel extends Kernel
{
    use MicroKernelTrait;

    const PDF_DIRECTORY = 'pdf';
    const XOD_DIRECTORY = 'web/xod';
    const IMAGE_DIRECTORY = 'web/images';

    protected $fileSystem;

    /**
     * @return array
     */
    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
        ];
    }

    /**
     * @param \Symfony\Component\Routing\RouteCollectionBuilder $routes
     */
    protected function configureRoutes(\Symfony\Component\Routing\RouteCollectionBuilder $routes)
    {
        $routes->add('/{filename}', 'kernel:PDFTronWebViewer');
    }

    /**
     * @param ContainerBuilder $c
     * @param LoaderInterface $loader
     */
    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $loader->load(__DIR__ . '/config/config_micro.yml');
        $loader->load(__DIR__ . '/config/services.yml');

        $c->loadFromExtension('framework', [
            'secret' => 'GoodToGoSecret',
        ]);
    }

  /**
   * @param $filename
   *
   * @return \Response
   */
  public function PDFTronWebViewer($filename)
    {
        // We're probably going to need the filesystem service.
        $this->fileSystem = $this->getContainer()->get('filesystem');

        // Convert the filename to system filepath
        // find the path to the PDF if it exists
        $PDFSystemPath = $this->getPDFSystemPath($filename);
        if (!empty($PDFSystemPath)) {
            return new Response('No such PDF file exists ' . $filename);
        }

        // Get the XOD system path.
        $XODSystemPath = $this->getXODSystemPath($filename);
        if (!$this->fileSystem->exists($XODSystemPath)) {
            // Load the PDFTron wrappers.
            // You could also loal in to your composer.json
            // eg:
            // "files": [ "PDFNetWrappers/PDFNetC/Lib/PDFNetPHP.php" ]
            require_once(__DIR__ . '/../PDFNetWrappers/PDFNetC/Lib/PDFNetPHP.php');

            // The first thing when using PDFTron is to initialize the library.
            // If you had a license you would pass the license key in here
            // \PDFNet::Initialize($yourLicenseKey);
            \PDFNet::Initialize();

            // Add some options.
            // Not required
            $xodOptions = new \XODOutputOptions();

            try {
                \Convert::ToXOD($PDFSystemPath, $XODSystemPath, $xodOptions);
            } catch (\Exception $e) {
                return new Response('Ooops unable to create XOD file!!');
            }

            // Generate a thumbnail from the first page of the PDF.
            $imageSystemPath = '';
            $dpi = 72;
            $imageType = 'JPEG';
            $page = 1; // We can select any page in the PDF.

            $doc = new \PDFDoc($PDFSystemPath);

            // should be called immediately after an encrypted document is opened
            $doc->InitSecurityHandler();

            // Get the page.
            $PDFPage = $doc->GetPage($page);

            // Create an image from the PDF.
            try {
                $draw = new \PDFDraw();
                $draw->SetDPI($dpi);
                $draw->Export($PDFPage, $imageSystemPath, $imageType);
            } catch (\Exception $e) {
                return new Response('Ooops unable to create the image file!!');
            }


            $doc->Close();

        }

        // Web path to the XOD file.
        $path = $this->getXODWebPath($filename);

        // Render the Web-viewer.
        return new Response($this->getContainer()->get('templating')->render('webviewer.html.twig', [
                'xodPath' => $path]
        ));
    }

  /**
   * @param $filename
   *
   * @return string
   */
  protected function getXODWebPath($filename)
    {
        $this->appendExtensionIfMissing($filename, 'xod');

        return 'xod/' . $filename;
    }

  /**
   * @param $filename
   *
   * @return string
   */
  protected function getXODSystemPath($filename)
    {
        $this->appendExtensionIfMissing($filename, 'xod');
        $rootDirectory = $this->getContainer()->getParameter('kernel.root_dir');

        return $rootDirectory . '/../' . self::XOD_DIRECTORY . '/' . $filename;
    }

  /**
   * @param $filename
   *
   * @return bool|string
   */
  protected function getPDFSystemPath($filename)
    {
        $this->appendExtensionIfMissing($filename, 'pdf');

        $rootDirectory = $this->getContainer()->getParameter('kernel.root_dir');
        $PDFDirectory = $rootDirectory . '/../' . self::PDF_DIRECTORY;
        $PDFPath = $PDFDirectory . '/' . $filename;

        if ($this->fileSystem->exists($PDFPath)) {
            return $PDFPath;
        }

        return false;
    }

  /**
   * @param        $filename
   * @param string $extension
   */
  protected function appendExtensionIfMissing(&$filename, $extension = 'pdf')
    {
        if (!preg_match('/(\.' . $extension . ')$/i', $filename)) {
            $filename .= '.' . $extension;
        }
    }
}