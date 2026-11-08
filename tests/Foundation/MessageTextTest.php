<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;

use Cor\Mgmt\Application\Communication\CommunicationException;
use Cor\Mgmt\Domain\MailSender;
use Cor\Mgmt\Domain\MessageText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageTextTest extends TestCase
{
    public function testReplacementDoesNotInterpretRecipientTokensRecursively():void
    {
        $text=new MessageText('Hello %name%','%name% / %team_name%');
        self::assertSame(['subject'=>'Hello %team_name%','message'=>'%team_name% / Team'],$text->render(['name'=>'%team_name%','team_name'=>'Team']));
    }
    #[DataProvider('senders')]
    public function testInvalidSenderCannotInjectHeaders(string $name,string $email):void
    {
        $this->expectException(CommunicationException::class);new MailSender($name,$email);
    }
    public static function senders():array{return [["Name\r\nBcc: stolen@example.invalid",'valid@example.invalid'],['Name',"valid@example.invalid\nBcc: stolen@example.invalid"],['','valid@example.invalid'],['Name','invalid'],['Name','<valid@example.invalid>']];}
    public function testQuotedDisplayNameIsEscaped():void
    {
        $headers=(new MailSender('A "quoted" name','sender@example.invalid'))->headers();
        self::assertStringContainsString('A \\"quoted\\" name',$headers);
        self::assertStringContainsString("\r\nContent-Type: text/plain; charset=UTF-8",$headers);
    }
    public function testRecipientNameCannotCreateAnotherHeader():void
    {
        $this->expectException(CommunicationException::class);(new MessageText('Hi %name%','Body'))->render(['name'=>"Name\r\nBcc: stolen@example.invalid"]);
    }
}
