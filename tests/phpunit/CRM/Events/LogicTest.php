<?php

use Civi\Api4\Contact;
use Civi\Api4\Event;
use Civi\Api4\OptionValue;
use Civi\Api4\Participant;
use Civi\Api4\ParticipantStatusType;
use Civi\Test;
use Civi\Test\CiviEnvBuilder;
use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;

/**
 * @group headless
 * @covers \CRM_Events_Logic
 */
class CRM_Events_LogicTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, TransactionalInterface {

  private int $eventType;

  private int $seminarCount = 0;

  public function setUpHeadless(): CiviEnvBuilder {
    return Test::headless()
      ->install('de.systopia.xcm')
      ->install('de.systopia.identitytracker')
      ->install('de.systopia.remotetools')
      ->install('de.systopia.remoteevent')
      ->installMe(__DIR__)
      ->apply();
  }

  protected function setUp(): void {
    parent::setUp();

    ParticipantStatusType::create(FALSE)
      ->addValue('name', 'entschuldigt')
      ->addValue('label', 'entschuldigt abwesend')
      ->addValue('class', 'Negative')
      ->addValue('is_active', TRUE)
      ->addValue('is_counted', FALSE)
      ->execute();

    $this->eventType = (int) OptionValue::create(FALSE)
      ->addValue('option_group_id:name', 'event_type')
      ->addValue('label', 'BUND Test Seminar')
      ->execute()
      ->single()['value'];

    $registeredId = (int) ParticipantStatusType::get(FALSE)
      ->addSelect('id')
      ->addWhere('name', '=', 'Registered')
      ->execute()
      ->single()['id'];

    \Civi::settings()->set('bund_event_types', [$this->eventType]);
    \Civi::settings()->set('bund_event_participant_status_types', [$registeredId]);
  }

  public function testExcusedCountsAsUsedAndBlocksOverbooking(): void {
    $contactId = $this->createFreiwillige(17);

    $this->participate($contactId, $this->createSeminar(1), 'entschuldigt');
    $this->participate($contactId, $this->createSeminar(3), 'entschuldigt');
    $this->participate($contactId, $this->createSeminar(5), 'entschuldigt');
    $this->participate($contactId, $this->createSeminar(4), 'entschuldigt');
    $this->participate($contactId, $this->createSeminar(2), 'Registered');
    $this->participate($contactId, $this->createSeminar(2), 'Registered');

    CRM_Events_Logic::updateContactEventStats($contactId);
    $stats = $this->readStats($contactId);
    static::assertSame(13, $stats['used'], 'excused-absent days must count as used');
    static::assertSame(4, $stats['booked']);
    static::assertSame(0, $stats['left']);

    $this->participate($contactId, $this->createSeminar(5), 'No-show');
    CRM_Events_Logic::updateContactEventStats($contactId);
    $stats = $this->readStats($contactId);
    static::assertSame(13, $stats['used'], 'unexcused No-show must not count as used');
    static::assertSame(0, $stats['left']);

    $newSeminar = $this->createSeminar(2);
    static::assertFalse(
      CRM_Events_Logic::contactStillHasContingentLeftForEvent(
        $contactId,
        ['id' => $newSeminar, 'event_type_id' => $this->eventType]
      ),
      'booking must be refused once the contingent is used up'
    );
  }

  private function createFreiwillige(int $pflicht): int {
    return (int) Contact::create(FALSE)
      ->addValue('contact_type', 'Individual')
      ->addValue('contact_sub_type', ['Freiwillige'])
      ->addValue('first_name', 'Test')
      ->addValue('last_name', 'Freiwillige')
      ->addValue('freiwillige_zusatzinfos.freiwillige_seminar_tage_pflicht', $pflicht)
      ->execute()
      ->single()['id'];
  }

  private function createSeminar(int $days): int {
    $month = sprintf('%02d', ++$this->seminarCount);
    return (int) Event::create(FALSE)
      ->addValue('title', "Seminar {$days}d")
      ->addValue('event_type_id', $this->eventType)
      ->addValue('start_date', "2020-{$month}-01")
      ->addValue('is_active', TRUE)
      ->addValue('seminar_zusatzinfo.seminar_gesamtzahl_tage', $days)
      ->execute()
      ->single()['id'];
  }

  private function participate(int $contactId, int $eventId, string $status): void {
    Participant::create(FALSE)
      ->addValue('contact_id', $contactId)
      ->addValue('event_id', $eventId)
      ->addValue('status_id:name', $status)
      ->execute();
  }

  /**
   * @return array{used: int, booked: int, left: int}
   */
  private function readStats(int $contactId): array {
    $contact = Contact::get(FALSE)
      ->addSelect(
        'freiwillige_zusatzinfos.freiwillige_seminar_tage_geleistet',
        'freiwillige_zusatzinfos.freiwillige_seminar_tage_gebucht',
        'freiwillige_zusatzinfos.freiwillige_seminar_tage_offen'
      )
      ->addWhere('id', '=', $contactId)
      ->execute()
      ->single();
    return [
      'used' => (int) $contact['freiwillige_zusatzinfos.freiwillige_seminar_tage_geleistet'],
      'booked' => (int) $contact['freiwillige_zusatzinfos.freiwillige_seminar_tage_gebucht'],
      'left' => (int) $contact['freiwillige_zusatzinfos.freiwillige_seminar_tage_offen'],
    ];
  }

}
