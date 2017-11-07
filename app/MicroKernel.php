<?php

use Symfony\Component\HttpKernel\Kernel;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\TwigBundle\TwigBundle;

/**
 * Class MicroKernel
 */
class MicroKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return array
     */
    public function registerBundles()
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new IrishDan\PDFTronBundle\PDFTronBundle(),
        ];
    }

    /**
     * @param \Symfony\Component\Routing\RouteCollectionBuilder $routes
     */
    protected function configureRoutes(\Symfony\Component\Routing\RouteCollectionBuilder $routes)
    {
        $routes->add('/{filename}', 'kernel:XODWebViewer');
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
            'secret' => 'OiChoiOi',
        ]);
    }

    /**
     * @param $filename
     * @return Response
     */
    public function XODWebViewer($filename)
    {
        // Convert the filename to system filepath
        // find the path of the converted XOD.
        $PDFFileSystem = $this->getContainer()->get('pdftron.file_system');
        $fileMapping = $PDFFileSystem->getPDFToXODFileMapping($filename);

        // If the XOD already exists
        // render it in the twig template.
        // If it doesn't exist,
        // create it from the PDf and
        // render it in the twig template.
        if (!$PDFFileSystem->exists($fileMapping->XODPath)) {
            // Check if the pdf exists,
            // if it does use it to create the xod
            if ($PDFFileSystem->exists($fileMapping->PDFPath)) {
                try {
                    $PDFConverter = $this->getContainer()->get('pdftron.pdf_to_xod');
                    $PDFConverter->convertPDFToXOD($fileMapping);
                } catch (\Exception $e) {
                    return new Response($e->getMessage());
                }
            } else {
                return new Response('No such PDF file exists ' . $filename . '.pdf');
            }
        }

        // Web path to the XOD file.
        $path = $PDFFileSystem->getXODWebPath($filename);

        // Render the Web-viewer.
        return new Response($this->getContainer()->get('templating')->render('webviewer.html.twig', [
                'xodPath' => $path]
        ));
    }
}