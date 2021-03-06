<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\Argument\Token\TypeToken;
use Sonata\AdminBundle\Admin\BreadcrumbsBuilderInterface;
use Sonata\AdminBundle\Admin\Pool as AdminPool;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Templating\TemplateRegistryInterface;
use Sonata\ClassificationBundle\Model\CategoryInterface;
use Sonata\MediaBundle\Admin\BaseMediaAdmin;
use Sonata\MediaBundle\Controller\MediaAdminController;
use Sonata\MediaBundle\Model\CategoryManagerInterface;
use Sonata\MediaBundle\Provider\Pool;
use Sonata\MediaBundle\Tests\Entity\Media;
use Sonata\MediaBundle\Tests\Fixtures\EntityWithGetId;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

class MediaAdminControllerTest extends TestCase
{
    private $container;
    private $admin;
    private $request;
    private $controller;

    protected function setUp(): void
    {
        $this->container = $this->prophesize(ContainerInterface::class);
        $this->admin = $this->prophesize(BaseMediaAdmin::class);
        $this->request = $this->prophesize(Request::class);

        $this->configureCRUDController();

        $this->controller = new MediaAdminController();
        $this->controller->setContainer($this->container->reveal());
    }

    public function testCreateActionToSelectProvider(): void
    {
        $pool = $this->prophesize(Pool::class);

        $this->configureRender(
            '@SonataMedia/MediaAdmin/select_provider.html.twig',
            Argument::type('array'),
            'renderResponse'
        );
        $pool->getProvidersByContext('context')->willReturn(['provider']);
        $pool->getDefaultContext()->willReturn('default_context');
        $this->admin->checkAccess('create')->shouldBeCalled();
        $this->container->get('sonata.media.pool')->willReturn($pool->reveal());
        $this->request->get('provider')->willReturn(false);
        $this->request->isMethod('get')->willReturn(true);
        $this->request->get('context', 'default_context')->willReturn('context');

        $response = $this->controller->createAction($this->request->reveal());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('renderResponse', $response->getContent());
    }

    public function testCreateAction(): void
    {
        $this->configureCreateAction(Media::class);
        $this->configureRender('template', Argument::type('array'), 'renderResponse');
        $this->admin->checkAccess('create')->shouldBeCalled();
        $this->request->get('provider')->willReturn(true);
        $this->request->isMethod('get')->willReturn(true);
        $response = $this->controller->createAction($this->request->reveal());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('renderResponse', $response->getContent());
    }

    public function testListAction(): void
    {
        $datagrid = $this->prophesize(DatagridInterface::class);
        $pool = $this->prophesize(Pool::class);
        $categoryManager = $this->prophesize(CategoryManagerInterface::class);
        $category = $this->prophesize();
        $category->willExtend(EntityWithGetId::class);
        $category->willImplement(CategoryInterface::class);
        $form = $this->prophesize(Form::class);
        $formView = $this->prophesize(FormView::class);

        $this->configureSetFormTheme($formView->reveal(), ['filterTheme']);
        $this->configureSetCsrfToken('sonata.batch');
        $this->configureRender('templateList', Argument::type('array'), 'renderResponse');
        $datagrid->setValue('context', null, 'another_context')->shouldBeCalled();
        $datagrid->setValue('category', null, 1)->shouldBeCalled();
        $datagrid->getForm()->willReturn($form->reveal());
        $pool->getDefaultContext()->willReturn('context');
        $categoryManager->getRootCategory('another_context')->willReturn($category->reveal());
        $categoryManager->findOneBy([
            'id' => 2,
            'context' => 'another_context',
        ])->willReturn($category->reveal());
        $category->getId()->willReturn(1);
        $form->createView()->willReturn($formView->reveal());
        $this->container->get('sonata.media.pool')->willReturn($pool->reveal());
        $this->container->has('sonata.media.manager.category')->willReturn(true);
        $this->container->get('sonata.media.manager.category')->willReturn($categoryManager->reveal());
        $this->admin->checkAccess('list')->shouldBeCalled();
        $this->admin->setListMode('mosaic')->shouldBeCalled();
        $this->admin->getDatagrid()->willReturn($datagrid->reveal());
        $this->admin->getPersistentParameter('context', 'context')->willReturn('another_context');
        $this->admin->getFilterTheme()->willReturn(['filterTheme']);
        $this->admin->getTemplate('list')->willReturn('templateList');
        $this->request->get('_list_mode', 'mosaic')->willReturn('mosaic');
        $this->request->get('filter')->willReturn([]);
        $this->request->get('category')->willReturn(2);

        $response = $this->controller->listAction($this->request->reveal());

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('renderResponse', $response->getContent());
    }

    private function configureCRUDController(): void
    {
        $pool = $this->prophesize(AdminPool::class);
        $breadcrumbsBuilder = $this->prophesize(BreadcrumbsBuilderInterface::class);
        $templateRegistry = $this->prophesize(TemplateRegistryInterface::class);

        $this->configureGetCurrentRequest($this->request->reveal());
        $pool->getAdminByAdminCode('admin_code')->willReturn($this->admin->reveal());
        $this->request->isXmlHttpRequest()->willReturn(false);
        $this->request->get('_xml_http_request')->willReturn(false);
        $this->request->get('_sonata_admin')->willReturn('admin_code');
        $this->request->get('uniqid')->shouldBeCalled();
        $this->container->get('sonata.admin.pool')->willReturn($pool->reveal());
        $this->container->get('sonata.admin.breadcrumbs_builder')->willReturn($breadcrumbsBuilder->reveal());
        $this->container->get('admin_code.template_registry')->willReturn($templateRegistry);
        $this->admin->getTemplate('layout')->willReturn('layout.html.twig');
        $this->admin->isChild()->willReturn(false);
        $this->admin->setRequest($this->request->reveal())->shouldBeCalled();
        $this->admin->getCode()->willReturn('admin_code');
    }

    private function configureCreateAction(string $class): void
    {
        $object = $this->prophesize(Media::class);
        $form = $this->prophesize(Form::class);
        $formView = $this->prophesize(FormView::class);

        $this->configureSetFormTheme($formView->reveal(), ['formTheme']);
        $this->admin->hasActiveSubClass()->willReturn(false);
        $this->admin->getClass()->willReturn($class);
        $this->admin->getNewInstance()->willReturn($object->reveal());
        $this->admin->setSubject($object->reveal())->shouldBeCalled();
        $this->admin->getForm()->willReturn($form->reveal());
        $this->admin->getFormTheme()->willReturn(['formTheme']);
        $this->admin->getTemplate('edit')->willReturn('template');
        $form->createView()->willReturn($formView->reveal());
        $form->setData($object->reveal())->shouldBeCalled();
        $form->handleRequest($this->request->reveal())->shouldBeCalled();
        $form->isSubmitted()->willReturn(false);
        $form->all()->willReturn(['field' => null]);
    }

    private function configureGetCurrentRequest(Request $request): void
    {
        $requestStack = $this->prophesize(RequestStack::class);

        $this->container->has('request_stack')->willReturn(true);
        $this->container->get('request_stack')->willReturn($requestStack->reveal());
        $requestStack->getCurrentRequest()->willReturn($request);
    }

    private function configureSetFormTheme(FormView $formView, $formTheme): void
    {
        $twig = $this->prophesize(Environment::class);

        $rendererClass = FormRenderer::class;

        $twigRenderer = $this->prophesize($rendererClass);

        $this->container->get('twig')->willReturn($twig->reveal());

        $twig->getRuntime($rendererClass)->willReturn($twigRenderer->reveal());

        $twigRenderer->setTheme($formView, $formTheme)->shouldBeCalled();
    }

    private function configureSetCsrfToken(string $intention): void
    {
        $tokenManager = $this->prophesize(CsrfTokenManagerInterface::class);
        $token = $this->prophesize(CsrfToken::class);

        $tokenManager->getToken($intention)->willReturn($token->reveal());
        $token->getValue()->willReturn('token');
        $this->container->has('security.csrf.token_manager')->willReturn(true);
        $this->container->get('security.csrf.token_manager')->willReturn($tokenManager->reveal());
    }

    private function configureRender(string $template, TypeToken $data, string $rendered): void
    {
        $templating = $this->prophesize(EngineInterface::class);
        $response = $this->prophesize(Response::class);
        $pool = $this->prophesize(Pool::class);

        $this->admin->getPersistentParameters()->willReturn(['param' => 'param']);
        $this->container->has('templating')->willReturn(true);
        $this->container->get('templating')->willReturn($templating->reveal());
        $this->container->get('sonata.media.pool')->willReturn($pool->reveal());
        $response->getContent()->willReturn($rendered);
        $templating->renderResponse($template, $data, null)->willReturn($response->reveal());
        $templating->render($template, $data)->willReturn($rendered);
    }
}
