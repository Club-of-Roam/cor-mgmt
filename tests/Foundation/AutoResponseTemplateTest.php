<?php

declare(strict_types=1);
namespace Cor\Mgmt\Tests\Foundation;

use Cor\Mgmt\Domain\AutoResponseTemplate;
use Cor\Mgmt\Application\Communication\CommunicationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AutoResponseTemplateTest extends TestCase
{
    public function testLanguageAliasesHaveOneCanonicalKey():void
    {
        $a=new AutoResponseTemplate('invitation','de-DE',true,'Invitation','Body');$b=new AutoResponseTemplate('invitation','de_de',true,'Invitation','Body');self::assertSame('de_de_formal',AutoResponseTemplate::language('de_DE_formal'));self::assertSame('de_de',$a->language);self::assertSame($a->language,$b->language);
    }
    public function testReplacementDoesNotRecursivelyInterpretUserValues():void
    {
        $template=new AutoResponseTemplate('invitation','en',true,'%event_name% / %team_name%','%name% %code% 100%');$rendered=$template->render(['event_name'=>'A','team_name'=>'Synthetic','name'=>'Name %code%','code'=>'123']);self::assertSame('A / Synthetic',$rendered['subject']);self::assertSame('Name %code% 123 100%',$rendered['message']);
    }
    public static function invalid():array
    {
        return [['bad-action','en',true,'Subject','Body'],['invitation','../en',true,'Subject','Body'],['invitation','en',true,"Subject\r\nBcc: x",'Body'],['invitation','en',true,'','Body'],['invitation','en',true,'Subject',''],['invitation','en',false,'Subject','%unknown%'],['invitation','en',true,'Subject',"a\0b"]];
    }
    #[DataProvider('invalid')]
    public function testInvalidTemplatesAreRejected(string $action,string $language,bool $enabled,string $subject,string $message):void
    {
        $this->expectException(CommunicationException::class);new AutoResponseTemplate($action,$language,$enabled,$subject,$message);
    }
    public function testDisabledBlankTemplateIsAnExplicitIndependentConfiguration():void
    {
        $template=new AutoResponseTemplate('team-creation','en',false,'','');self::assertFalse($template->enabled);self::assertSame(['subject'=>'','message'=>''],$template->render([]));
    }
    public function testMissingValuesAreNeverReplacedWithAnotherEventsDefaults():void
    {
        $template=new AutoResponseTemplate('team-creation','en',true,'%event_name%','%team_name%');$this->expectExceptionMessage('missing_placeholder');$template->render(['team_name'=>'Team']);
    }
    public function testHeaderInjectionThroughPlaceholdersIsRejected():void
    {
        $template=new AutoResponseTemplate('team-creation','en',true,'%name%','Body');$this->expectExceptionMessage('invalid_rendered_template');$template->render(['name'=>"Person\nBcc: bad"]);
    }
    public function testEveryLegacyActionAndPlaceholderRemainsRecognized():void
    {
        foreach(AutoResponseTemplate::ACTIONS as $action){$template=new AutoResponseTemplate($action,'en',true,'Subject',implode(' ',array_map(static fn(string $field):string=>'%'.$field.'%',AutoResponseTemplate::FIELDS)));$values=array_fill_keys(AutoResponseTemplate::FIELDS,'synthetic');self::assertStringNotContainsString('%',$template->render($values)['message']);}
    }
}
