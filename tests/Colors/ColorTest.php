<?php

namespace Colors\Test;

use PHPUnit\Framework\TestCase;
use Colors\Color;
use Colors\NoStyleFoundException;
use Colors\InvalidStyleNameException;
use Colors\RecursionInUserStylesException;

function color($string = '')
{
    return new Color($string);
}

class ColorsTest extends TestCase
{
    public function testGivenStringShouldApplyStyle()
    {
        $this->assertSame("\033[31mfoo\033[0m", (string) color('foo')->red());
    }

    public function testGivenStringShouldApplyMoreThanOneStyle()
    {
        $this->assertSame("\033[1m\033[97mfoo\033[0m\033[0m", (string) color('foo')->white()->bold());
    }

    public function testStyleNameIsNotCaseSensitive()
    {
        $this->assertSame("\033[31mfoo\033[0m", (string) color('foo')->RED());
    }

    public function testStateIsInitializedForSuccessiveCalls()
    {
        $color = new Color();
        $this->assertSame('foo', (string) $color('foo'));
        $this->assertSame('bar', (string) $color('bar'));
    }

    public function testGivenStyledStringShouldBeAbleToResetIt()
    {
        $this->assertSame('foo', (string) color('foo')->blue()->reset());
    }

    public function testThrowsExceptionForUnknownStyle()
    {
        try {
            color('foo bar')->foo();
            $this->fail('Must throw an exception');
        } catch (NoStyleFoundException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
            $this->assertEquals('Invalid style foo', $e->getMessage());
        }
    }

    public function testCanDirectlyCallStyleMethodOnText()
    {
        $this->assertSame((string) color('foo')->blue(), color()->blue('foo'));
    }

    public function testHasShortcutForForegroundColor()
    {
        $this->assertSame((string) color('Hello')->blue(), (string) color('Hello')->fg('blue'));
    }

    public function testHasShortcutForBackgroundColor()
    {
        $this->assertSame((string) color('Hello')->bg_red(), (string) color('Hello')->bg('red'));
    }

    public function testHasHighlightShortcutForBackgroundColor()
    {
        $this->assertSame((string) color('Hello')->bg_blue(), (string) color('Hello')->highlight('blue'));
    }

    public function testHasPropertyShortcutForStyle()
    {
        $this->assertSame((string) color('Hello')->blue(), (string) color('Hello')->blue);
    }

    public function testShouldSupportUserStyles()
    {
        $color = new Color();
        $color->setUserStyles(array('error' => 'red'));

        $this->assertEquals((string) color('Error...')->red(), (string) $color('Error...')->error());
    }

    public function testUserStylesShouldOverrideDefaultStyles()
    {
        $color = new Color();
        $color->setUserStyles(array('white' => 'red'));

        $this->assertEquals((string) color('Warning...')->red, (string) $color('Warning...')->white);
    }

    public function testGivenInvalidUserStyleNameShouldThrowAnException()
    {
        $color = new Color();
        try {
            $color->setUserStyles(array('foo-bar' => 'red'));
            $this->fail('must throw an InvalidArgumentException');
        } catch (InvalidStyleNameException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
            $this->assertSame('foo-bar is not a valid style name', $e->getMessage());
        }
    }

    public function testGivenStyledStringWhenCleanedShouldStripAllStyles()
    {
        $this->assertEquals('some text', (string) color((string) color('some text')->red())->clean());
    }

    public function testHasStripShortcutForClean()
    {
        $this->assertEquals('some text', (string) color()->strip(color('some text')->red()));
    }

    public function testGivenThatStylesAreNotSupportedShouldReturnInputString()
    {
        $color = $this->getMock('colors\color', array('isSupported'));
        $color->expects($this->once())
            ->method('isSupported')
            ->will($this->returnValue(false));

        $this->assertSame('foo bar', (string) $color('foo bar')->red());
    }

    public function testGivenStringWithStyleTagsShouldInterpretThem()
    {
        $text = 'before <red>some text</red>';
        $this->assertSame('before ' . color('some text')->red(), (string) color($text)->colorize());
    }

    public function testGivenStringWithNestedStyleTagsShouldInterpretThem()
    {
        $actual = (string) color('<cyan>Hello <bold>World!</bold></cyan>')->colorize();
        $expected = (string) color('Hello ' . color('World!')->bold())->cyan();
        $this->assertSame($expected, $actual);
    }

    public function testAppliesStyleDirectlyToText()
    {
        $this->assertSame((string) color('foo')->blue(), color()->apply('blue', 'foo'));
    }

    public function testWhenApplyCenterToStringShouldCenterIt()
    {
        $width = 80;
        $color = new Color();

        foreach (array('', 'hello', 'hello world!', '✩') as $text) {
            $actualWidth = mb_strlen($color($text)->center($width)->__toString(), 'UTF-8');
            $this->assertSame($width, $actualWidth);
            $actualWidth = mb_strlen($color($text)->center($width)->bg('blue')->clean()->__toString(), 'UTF-8');
            $this->assertSame($width, $actualWidth);
        }
    }

    public function testWhenApplyCenterToMultilineStringShouldCenterIt()
    {
        $width = 80;
        $color = new Color();
        $text = 'hello' . PHP_EOL . '✩' . PHP_EOL . 'world';

        $actual = $color($text)->center($width)->__toString();
        foreach (explode(PHP_EOL, $actual) as $line) {
            $this->assertSame($width, mb_strlen($line, 'UTF-8'));
        }
    }

    public function testStylesAreAppliedWhenForced()
    {
        $color = $this->getMock('colors\color', array('isSupported'));
        $color->expects($this->any())
            ->method('isSupported')
            ->will($this->returnvalue(false));

        $color->setForceStyle(true);

        $this->assertTrue($color->isStyleForced());
        $this->assertSame((string) color('foo')->blue(), (string) $color('foo')->blue());
    }

    public function testShouldSupport256Colors()
    {
        $this->assertSame("\033[38;5;3mfoo\033[0m", color()->apply('color[3]', 'foo'));
        $this->assertSame("\033[48;5;3mfoo\033[0m", color()->apply('bg_color[3]', 'foo'));
    }

    public function testGivenInvalidColorNumberShouldThrowException()
    {
        try {
            color()->apply('color[-1]', 'foo');
            $this->fail('Must throw an exception');
        } catch (NoStyleFoundException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
            $this->assertEquals('Invalid style color[-1]', $e->getMessage());
        }

        try {
            color()->apply('color[256]', 'foo');
            $this->fail('Must throw an exception');
        } catch (NoStyleFoundException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
            $this->assertEquals('Invalid style color[256]', $e->getMessage());
        }
    }

    /**
     * Bug #10
     */
    public function testShouldHandleRecursionInTheme()
    {
        try {
            color()->setTheme(
                array(
                    'green' => array('green'),
                )
            );
            $this->fail('Must throw an exception');
        } catch (RecursionInUserStylesException $e) {
            $this->assertInstanceOf('InvalidArgumentException', $e);
            $this->assertEquals('User style cannot reference itself.', $e->getMessage());
        }
    }
}
