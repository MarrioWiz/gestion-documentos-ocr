<?php

namespace Tests\Unit;

use App\Services\ClasificadorDocumentos;
use App\Services\DocumentoOcrService;
use App\Services\NombreEnCurp;
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

    /**
     * Caso real: en la constancia descargada de gob.mx el texto sale
     * desordenado (la etiqueta "Nombre" queda DESPUÉS del nombre) y arriba
     * viene la firma de la Secretaria de Gobernación. El nombre correcto se
     * elige porque es el único que cuadra con las letras de la CURP.
     */
    public function test_elige_el_nombre_que_cuadra_con_la_curp_y_no_la_firma_de_la_secretaria(): void
    {
        $texto = <<<'TXT'
            PRESENTE
            El derecho a la identidad está consagrado en nuestra Constitución.
            Agradezco tu participación.
            ROSA ICELA RODRÍGUEZ VELÁZQUEZ
            SECRETARIA DE GOBERNACIÓN
            Base de Datos Nacional de la Clave Única de Registro de Población (RENAPO)
            CARLOS DANIEL MENDOZA RUIZ
            130039200504058
            MERC010722HPLNZRA3
            CARLOS DANIEL MENDOZA RUIZ
            Clave:
            Nombre
            CURP Certificada: verificada con el Registro Civil
            PUEBLA
            Entidad de registro:
            TXT;

        $r = $this->servicio->analizarTexto($texto);

        $this->assertSame('curp', $r['tipo_documento']);
        $this->assertSame('MENDOZA RUIZ CARLOS DANIEL', $r['nombre_completo']);
    }

    public function test_nombre_partido_en_renglones_antes_de_sus_etiquetas(): void
    {
        $texto = <<<'TXT'
            Estados Unidos Mexicanos
            Acta de Nacimiento
            Datos de la Persona Registrada
            MARIA FERNANDA
            LOPEZ
            GARCIA
            Nombre(s):
            Primer Apellido:
            Segundo Apellido:
            Clave Única de Registro de Población
            LOGF951103MJCPRR08
            Datos de Filiación de la Persona Registrada
            ALBERTO LOPEZ DIAZ
            SOFIA GARCIA NAVA
            TXT;

        $r = $this->servicio->analizarTexto($texto);

        $this->assertSame('acta_nacimiento', $r['tipo_documento']);
        // No confunde a la persona con sus padres (mismos apellidos).
        $this->assertSame('LOPEZ GARCIA MARIA FERNANDA', $r['nombre_completo']);
    }

    /**
     * Foto borrosa real: el OCR mete basura corta ("c", "-", comillas)
     * entre los renglones del nombre.
     */
    public function test_reconoce_el_nombre_aunque_el_ocr_meta_basura_entre_renglones(): void
    {
        $texto = "LICENCIA PARA CONDUCIR\nTIPO\nCARLOS DANIEL E c\n- MENDOZA RUIZ '\nk BMOSO8024VZMBRA! L. Or";

        $this->assertSame('MENDOZA RUIZ CARLOS DANIEL', NombreEnCurp::enTexto($texto, 'MERC010722HPLNZRA3'));
        // El nombre de otra persona no cuadra con esa CURP.
        $this->assertNull(NombreEnCurp::enTexto($texto, 'LOGF951103MJCPRR08'));
    }

    public function test_un_nombre_que_no_cuadra_con_la_curp_no_se_marca_como_verificado(): void
    {
        // INE borrosa: la etiqueta "NOMBRE" va seguida del domicilio.
        $r = $this->servicio->analizarTexto("CREDENCIAL PARA VOTAR\nNOMBRE\nMARGARITA MAZA\nCURP MERC010722HPLNZRA3");

        $this->assertSame('MERC010722HPLNZRA3', $r['curp']);
        $this->assertFalse($r['nombre_verificado']);
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
