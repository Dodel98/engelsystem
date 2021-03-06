<?php

namespace Engelsystem\Test\Unit\Controllers\Admin;

use Engelsystem\Config\Config;
use Engelsystem\Controllers\Admin\NewsController;
use Engelsystem\Helpers\Authenticator;
use Engelsystem\Http\Exceptions\ValidationException;
use Engelsystem\Http\Request;
use Engelsystem\Http\Response;
use Engelsystem\Http\UrlGenerator;
use Engelsystem\Http\UrlGeneratorInterface;
use Engelsystem\Http\Validation\Validator;
use Engelsystem\Models\News;
use Engelsystem\Models\User\User;
use Engelsystem\Test\Unit\HasDatabase;
use Engelsystem\Test\Unit\TestCase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\Test\TestLogger;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class NewsControllerTest extends TestCase
{
    use HasDatabase;

    /** @var Authenticator|MockObject */
    protected $auth;

    /** @var array */
    protected $data = [
        [
            'title'      => 'Foo',
            'text'       => '<b>foo</b>',
            'is_meeting' => false,
            'user_id'    => 1,
        ]
    ];

    /** @var TestLogger */
    protected $log;

    /** @var Response|MockObject */
    protected $response;

    /** @var Request */
    protected $request;

    /**
     * @covers \Engelsystem\Controllers\Admin\NewsController::edit
     */
    public function testEditHtmlWarning()
    {
        $this->request->attributes->set('id', 1);
        $this->response->expects($this->once())
            ->method('withView')
            ->willReturnCallback(function ($view, $data) {
                $this->assertEquals('pages/news/edit.twig', $view);

                /** @var Collection $warnings */
                $warnings = $data['warnings'];
                $this->assertNotEmpty($data['news']);
                $this->assertTrue($warnings->isNotEmpty());
                $this->assertEquals('news.edit.contains-html', $warnings->first());

                return $this->response;
            });
        $this->addUser();

        /** @var NewsController $controller */
        $controller = $this->app->make(NewsController::class);

        $controller->edit($this->request);
    }

    /**
     * @covers \Engelsystem\Controllers\Admin\NewsController::__construct
     * @covers \Engelsystem\Controllers\Admin\NewsController::edit
     */
    public function testEdit()
    {
        $this->request->attributes->set('id', 1);
        $this->response->expects($this->once())
            ->method('withView')
            ->willReturnCallback(function ($view, $data) {
                $this->assertEquals('pages/news/edit.twig', $view);

                /** @var Collection $warnings */
                $warnings = $data['warnings'];
                $this->assertNotEmpty($data['news']);
                $this->assertTrue($warnings->isEmpty());

                return $this->response;
            });
        $this->auth->expects($this->once())
            ->method('can')
            ->with('admin_news_html')
            ->willReturn(true);

        /** @var NewsController $controller */
        $controller = $this->app->make(NewsController::class);

        $controller->edit($this->request);
    }

    /**
     * @covers \Engelsystem\Controllers\Admin\NewsController::save
     */
    public function testSaveCreateInvalid()
    {
        /** @var NewsController $controller */
        $controller = $this->app->make(NewsController::class);
        $controller->setValidator(new Validator());

        $this->expectException(ValidationException::class);
        $controller->save($this->request);
    }

    /**
     * @return array
     */
    public function saveCreateEditProvider(): array
    {
        return [
            ['Some <b>test</b>', true, true, 'Some <b>test</b>'],
            ['Some <b>test</b>', false, false, 'Some test'],
            ['Some <b>test</b>', false, true, 'Some <b>test</b>', 1],
            ['Some <b>test</b>', true, false, 'Some test', 1],
        ];
    }

    /**
     * @covers       \Engelsystem\Controllers\Admin\NewsController::save
     * @dataProvider saveCreateEditProvider
     *
     * @param string $text
     * @param bool $isMeeting
     * @param bool $canEditHtml
     * @param string $result
     * @param int|null $id
     */
    public function testSaveCreateEdit(
        string $text,
        bool $isMeeting,
        bool $canEditHtml,
        string $result,
        int $id = null
    ) {
        $this->request->attributes->set('id', $id);
        $id = $id ?: 2;
        $this->request = $this->request->withParsedBody([
            'title'      => 'Some Title',
            'text'       => $text,
            'is_meeting' => $isMeeting ? '1' : null,
        ]);
        $this->addUser();
        $this->auth->expects($this->once())
            ->method('can')
            ->with('admin_news_html')
            ->willReturn($canEditHtml);
        $this->response->expects($this->once())
            ->method('redirectTo')
            ->with('http://localhost/news/' . $id)
            ->willReturn($this->response);

        /** @var NewsController $controller */
        $controller = $this->app->make(NewsController::class);
        $controller->setValidator(new Validator());

        $controller->save($this->request);

        $this->assertTrue($this->log->hasInfoThatContains('Updated'));

        /** @var Session $session */
        $session = $this->app->get('session');
        $messages = $session->get('messages');
        $this->assertEquals('news.edit.success', $messages[0]);

        $news = (new News())->find($id);
        $this->assertEquals($result, $news->text);
        $this->assertEquals($isMeeting, (bool)$news->is_meeting);
    }

    /**
     * @covers \Engelsystem\Controllers\Admin\NewsController::save
     */
    public function testSaveDelete()
    {
        $this->request->attributes->set('id', 1);
        $this->request = $this->request->withParsedBody([
            'title'  => '.',
            'text'   => '.',
            'delete' => '1',
        ]);
        $this->response->expects($this->once())
            ->method('redirectTo')
            ->with('http://localhost/news')
            ->willReturn($this->response);

        /** @var NewsController $controller */
        $controller = $this->app->make(NewsController::class);
        $controller->setValidator(new Validator());

        $controller->save($this->request);

        $this->assertTrue($this->log->hasInfoThatContains('Deleted'));

        /** @var Session $session */
        $session = $this->app->get('session');
        $messages = $session->get('messages');
        $this->assertEquals('news.delete.success', $messages[0]);
    }

    /**
     * Setup environment
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->initDatabase();

        $this->request = Request::create('http://localhost');
        $this->app->instance('request', $this->request);
        $this->app->instance(Request::class, $this->request);
        $this->app->instance(ServerRequestInterface::class, $this->request);

        $this->response = $this->createMock(Response::class);
        $this->app->instance(Response::class, $this->response);

        $this->log = new TestLogger();
        $this->app->instance(LoggerInterface::class, $this->log);

        $this->app->instance('session', new Session(new MockArraySessionStorage()));

        $this->auth = $this->createMock(Authenticator::class);
        $this->app->instance(Authenticator::class, $this->auth);

        $this->app->bind(UrlGeneratorInterface::class, UrlGenerator::class);

        $this->app->instance('config', new Config());

        (new News([
            'title'      => 'Foo',
            'text'       => '<b>foo</b>',
            'is_meeting' => false,
            'user_id'    => 1,
        ]))->save();
    }

    /**
     * Creates a new user
     */
    protected function addUser()
    {
        $user = new User([
            'name'          => 'foo',
            'password'      => '',
            'email'         => '',
            'api_key'       => '',
            'last_login_at' => null,
        ]);
        $user->forceFill(['id' => 42]);
        $user->save();

        $this->auth->expects($this->any())
            ->method('user')
            ->willReturn($user);
    }
}
