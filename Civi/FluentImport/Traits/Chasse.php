<?php
namespace Civi\FluentImport\Traits;

use Civi\FluentImport;

trait Chasse {
  /**
   * Sets the chasse step and date, optionally unless the existing step matches the regex given.
   *
   * Use something lile '/./' to mean "any" journey.
   *
   * Outputs to context:
   * - chasse_updated TRUE|FALSE
   * - chasse_step
   * - chasse_not_before
   *
   * @return static
   */
  public function setChasseJourney($stepID = NULL, $notBeforeDate = NULL, $unlessRegex = NULL) {
    if (!$this->alive) return $this;

    if ($unlessRegex !== NULL) {

      $data = \Civi\Api4\Contact::get(FALSE)
        ->addSelect('chasse.chasse_step', 'chasse.chasse_not_before')
        ->addWhere('id', '=', $this->getContactID())
        ->execute()->first();

      if (preg_match($unlessRegex, $data['chasse.chasse_step'] ?? '')) {
        // We’re not supposed to update.
        $this->setContextValue('chasse_updated', FALSE);
        $this->setContextValue('chasse_step', $data['chasse.chasse_step'] ?? '');
        $this->setContextValue('chasse_not_before', $data['chasse.chasse_not_before'] ?? NULL);
        return $this;
      }
    }

    // Update/set the step.
    $data = \Civi\Api4\Contact::update(FALSE)
      ->addValue('chasse.chasse_step', $stepID)
      ->addValue('chasse.chasse_not_before', $notBeforeDate)
      ->addWhere('id', '=', $this->getContactID())
      ->addWhere('is_deleted', '=', 0)
      ->addWhere('is_deceased', '=', 0)
      ->execute()->first();
    $this->setContextValue('chasse_updated', TRUE);
    $this->setContextValue('chasse_step', $stepID);
    $this->setContextValue('chasse_not_before', $notBeforeDate);

    return $this;
  }
}
