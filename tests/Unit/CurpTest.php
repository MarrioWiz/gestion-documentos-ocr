<?php

namespace Tests\Unit;

use App\Services\Curp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CurpTest extends TestCase
{
    public function test_valida_estructura_y_digito_verificador(): void
    {
        $this->assertTrue(Curp::esValida('MERC010722HPLNZRA3'));
        $this->assertTrue(Curp::digitoVerificadorValido('MERC010722HPLNZRA3'));
        $this->assertFalse(Curp::digitoVerificadorValido('MERC010722HPLNZRA4'));
    }

    public function test_queretaro_usa_la_clave_oficial_qt(): void
    {
        $this->assertTrue(Curp::esValida('SAVJ870214HQTNLL03'));
        $this->assertFalse(Curp::esValida('SAVJ870214HQONLL03'));
        $this->assertSame('Querétaro', Curp::entidad('SAVJ870214HQTNLL03'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function erroresDeOcr(): array
    {
        return [
            'O en lugar de 0 en la fecha' => ['MERCO1O722HPLNZRA3', 'MERC010722HPLNZRA3'],
            'I en lugar de 1 en la fecha' => ['MERC0I0722HPLNZRA3', 'MERC010722HPLNZRA3'],
            '0 en lugar de O en las letras' => ['L0GF951103MJCPRR08', 'LOGF951103MJCPRR08'],
            'O en el diferenciador de alguien nacido en 1987' => ['SAVJ870214HQTNLLO3', 'SAVJ870214HQTNLL03'],
            '0 en el diferenciador de alguien nacido en 2001' => ['MERC010722HPLNZR03', 'MERC010722HPLNZRO3'],
        ];
    }

    #[DataProvider('erroresDeOcr')]
    public function test_corrige_confusiones_tipicas_del_ocr(string $leida, string $esperada): void
    {
        $resultado = Curp::corregir($leida);

        $this->assertSame($esperada, $resultado['curp']);
    }

    public function test_marca_como_no_verificada_una_curp_con_digito_incorrecto(): void
    {
        $resultado = Curp::corregir('GOVM800705MCLMLRO1');

        $this->assertSame('GOVM800705MCLMLR01', $resultado['curp']);
        $this->assertFalse($resultado['verificada']);
    }

    public function test_rechaza_texto_que_no_es_curp(): void
    {
        $this->assertNull(Curp::corregir('HOLA MUNDO')['curp']);
        $this->assertNull(Curp::corregir('XXXXXXXXXXXXXXXXXX')['curp']);
    }

    public function test_deriva_fecha_de_nacimiento_con_el_siglo_correcto(): void
    {
        $this->assertSame('1987-02-14', Curp::fechaNacimiento('SAVJ870214HQTNLL03'));
        $this->assertSame('2001-07-22', Curp::fechaNacimiento('MERC010722HPLNZRA3'));
    }

    public function test_verifica_el_nombre_contra_las_iniciales(): void
    {
        $this->assertTrue(Curp::coincideConNombre('MERC010722HPLNZRA3', 'MENDOZA', 'RUIZ', 'CARLOS DANIEL'));
        // Con "MARÍA" como primer nombre, RENAPO usa el segundo (FERNANDA).
        $this->assertTrue(Curp::coincideConNombre('LOGF951103MJCPRR08', 'LOPEZ', 'GARCIA', 'MARIA FERNANDA'));
        $this->assertFalse(Curp::coincideConNombre('MERC010722HPLNZRA3', 'RUIZ', 'MENDOZA', 'CARLOS'));
    }

    public function test_el_nombre_debe_cuadrar_tambien_en_las_consonantes_internas(): void
    {
        // Las 7 letras: M-E-R-C (iniciales y vocal) + N-Z-R (consonantes internas).
        $this->assertSame(7, Curp::puntajeNombre('MERC010722HPLNZRA3', 'MENDOZA', 'RUIZ', 'CARLOS DANIEL'));
        // El padre comparte apellido paterno pero no cuadra.
        $this->assertLessThan(Curp::COINCIDENCIAS_MINIMAS_NOMBRE, Curp::puntajeNombre('MERC010722HPLNZRA3', 'MENDOZA', 'PEREZ', 'JOSE LUIS'));
        // Partículas: "DE LA CRUZ" cuenta como "CRUZ".
        $this->assertSame(7, Curp::puntajeNombre('CUSA900101HDFRNN00', 'DE LA CRUZ', 'SANCHEZ', 'ANTONIO'));
    }
}
