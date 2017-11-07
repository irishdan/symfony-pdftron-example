<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Response;

class PDFController extends Controller
{
    /**
     * @Route(
     *     path = "pdf/{filename}",
     *     name = "pdf_view"
     * )
     * @Method("GET")
     */
    public function pdfAction($filename)
    {
        // $pdfTron = $this->get('pdftron.converter');
        $pdfTron = $this->get('pdftron.pdf_to_xod');

        // Look for .xod file
        $xod = $pdfTron->xodExists($filename);
        if ($xod) {
            return $this->webViewerResponse($filename);
        } // Look for the pdf file
        else {
            $pdf = $pdfTron->pdfExists($filename);
            if ($pdf) {
                $pdfTron->convert($filename);
                return $this->webViewerResponse($filename);
            } else {
                return new Response('No such file exists ' . $filename . '.pdf');
            }
        }
    }

    public function webViewerResponse($filename)
    {

    }
}
