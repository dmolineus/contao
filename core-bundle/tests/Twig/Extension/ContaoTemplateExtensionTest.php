<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Twig\Extension;

use Contao\BackendCustom;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Tests\TestCase;
use Contao\CoreBundle\Twig\Extension\ContaoTemplateExtension;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Twig\TwigFunction;

class ContaoTemplateExtensionTest extends TestCase
{
    public function testRendersTheContaoBackendTemplate(): void
    {
        $template = new \stdClass();

        $backendRoute = $this->createMock(BackendCustom::class);
        $backendRoute
            ->expects($this->once())
            ->method('getTemplateObject')
            ->willReturn($template)
        ;

        $backendRoute
            ->expects($this->once())
            ->method('run')
            ->willReturn(new Response())
        ;

        $framework = $this->mockContaoFramework();
        $framework
            ->method('createInstance')
            ->with(BackendCustom::class)
            ->willReturn($backendRoute)
        ;

        $extension = $this->getExtension($framework);
        $extension->renderContaoBackendTemplate(['a' => 'a', 'b' => 'b', 'c' => 'c']);

        $this->assertSame('a', $template->a);
        $this->assertSame('b', $template->b);
        $this->assertSame('c', $template->c);
    }

    public function testAddsTheRenderContaoBackEndTemplateFunction(): void
    {
        $functions = $this->getExtension()->getFunctions();

        $renderBaseTemplateFunction = array_filter(
            $functions,
            static function (TwigFunction $function): bool {
                return 'render_contao_backend_template' === $function->getName();
            }
        );

        $this->assertCount(1, $renderBaseTemplateFunction);
    }

    public function testDoesNotRenderTheBackEndTemplateIfNotInBackEndScope(): void
    {
        $this->assertEmpty($this->getExtension(null, 'frontend')->renderContaoBackendTemplate());
    }

    /**
     * @param ContaoFramework&MockObject $framework
     */
    private function getExtension(ContaoFramework $framework = null, string $scope = 'backend'): ContaoTemplateExtension
    {
        $request = new Request();
        $request->attributes->set('_scope', $scope);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        if (null === $framework) {
            $framework = $this->mockContaoFramework();
        }

        return new ContaoTemplateExtension($requestStack, $framework, $this->mockScopeMatcher());
    }
}
