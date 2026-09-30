<?php

namespace Tests\Unit;

use App\Services\ClasificadorDocumentos;
use App\Services\DocumentoOcrService;
use PHPUnit\Framework\TestCase;

/**
 * Prueba la extracción sobre textos como los que devuelve Tesseract, sin
 * correr el OCR (rápido y sin dependencias externas).
 */
class AnalisisTextoTest extends TestCase
{
    private DocumentoOcrService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new DocumentoOcrService(new ClasificadorDocumentos);
    }

    public function test_extrae_los_datos_de_una_ine(): void
    {
        $texto = <<<'TXT'
            MÉXICO INSTITUTO NACIONAL ELECTORAL
            CREDENCIAL PARA VOTAR
            NOMBRE SEXO H
            MENDOZA
            RUIZ
            CARLOS DANIEL
            DOMICILIO
            C 5 DE MAYO 123 COL CENTRO
            CLAVE DE ELECTOR MNRZCR01072221H300
            CURP MERC010722HPLNZRA3
            FECHA DE NACIMIENTO 22/07/2001
            TXT;

        $r = $this->servicio->analizarTexto($texto);

        $this->assertSame('ine', $r['tipo_documento']);
        $this->assertSame('MERC010722HPLNZRA3', $r['curp']);
        $this->assertTrue($r['curp_verificada']);
        $this->assertSame('MENDOZA RUIZ CARLOS DANIEL', $r['nombre_completo']);
        $this->assertSame('MNRZCR01072221H300', $r['numero_documento']);
        $this->assertSame('2001-07-22', $r['fecha_nacimiento']);
        $this->assertSame('Puebla', $r['entidad_nacimiento']);
    }

    public function test_un_acta_no_se_confunde_con_curp_aunque_traiga_la_curp_impresa(): void
    {
        $texto = <<<'TXT'
            ESTADOS UNIDOS MEXICANOS
            ACTA DE NACIMIENTO
            Entidad de registro Municipio de registro Oficialía Fecha de registro Libro Número de acta
            PUEBLA PUEBLA 0001 15/08/2001 0003 01234
            Datos de la persona registrada
            Nombre(s): CARLOS DANIEL Primer apellido: MENDOZA Segundo apellido: RUIZ
            Sexo: HOMBRE Fecha de nacimiento: 22/07/2001
            CURP: MERC010722HPLNZRA3
            Datos de filiación de la persona registrada
            Progenitor 1: JOSE LUIS MENDOZA PEREZ
            TXT;

        $r = $this->servicio->analizarTexto($texto);

        $this->assertSame('acta_nacimiento', $r['tipo_documento']);
        $this->assertSame('MERC010722HPLNZRA3', $r['curp']);
        // El acta pone el nombre primero; se reordena con las iniciales de la CURP.
        $this->assertSame('MENDOZA RUIZ CARLOS DANIEL', $r['nombre_completo']);
        $this->assertSame('01234', $r['numero_documento']);
    }

    public function test_la_constancia_de_curp_no_se_confunde_con_acta_aunque_la_mencione(): void
    {
        $texto = <<<'TXT'
            CONSTANCIA DE LA CLAVE ÚNICA DE REGISTRO DE POBLACIÓN
            Clave: MERC010722HPLNZRA3
            Nombre
            CARLOS DANIEL MENDOZA RUIZ
            Datos del documento probatorio
            Acta de nacimiento Año de registro 2001 Número de acta 01234
            Registro Nacional de Población (RENAPO)
            TXT;

        $r = $this->servicio->analizarTexto($texto);

        $this->assertSame('curp', $r['tipo_documento']);
        $this->assertSame('MERC010722HPLNZRA3', $r['numero_documento']);
        $this->assertSame('MENDOZA RUIZ CARLOS DANIEL', $r['nombre_completo']);
    }

    public function test_texto_sin_firmas_conocidas_no_se_clasifica(): void
    {
        $r = $this->servicio->analizarTexto("LISTA DEL SUPERMERCADO\nLECHE\nPAN");

        $this->assertNull($r['tipo_documento']);
        $this->assertNull($r['curp']);
    }

    public function test_recupera_la_curp_aunque_el_ocr_la_parta_con_espacios(): void
    {
        $r = $this->servicio->analizarTexto("CREDENCIAL PARA VOTAR\nCURP MERC 0107 22HPLN ZRA3");

        $this->assertSame('MERC010722HPLNZRA3', $r['curp']);
    }
}
