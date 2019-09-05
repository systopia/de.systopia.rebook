<?php
use CRM_Rebook_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_Rebook_Upgrader extends CRM_Rebook_Upgrader_Base {

  /**
   * Run menu/rebuild
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_0130() {
    $this->ctx->log->info('Updating to 1.3');
    CRM_Core_Invoke::rebuildMenuAndCaches();
    return TRUE;
  }

}
