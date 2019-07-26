<div class="crm-block crm-content-block">
  <h2>Set Chassé step for these contacts</h2>

  <div>
    <span>{$form.new_step.label}</span>
    <span>{$form.new_step.html}</span>
  </div>

  <p>You might want only to set the step for contacts who don't currently have
  one. i.e. they are not currently on a journey.</p>

  <p>If so, tick the box below, otherwise the step you specify below will be
  applied to all the contacts regardless.  (Note that if you only want to
  change contacts on a particular step you can specify that using the Advanced
  Search, however that does not provide a way to search for 'no step', which is
  why this option exists here.)</p>

  <div>
    <span>{$form.only_if_blank.html}</span>
    <span>{$form.only_if_blank.label}</span>
  </div>

  <p>Do you want to set a delay before processing this step?</p>
  <div>
    {$form.set_delay.date.html}
    <div id="not_before_date" style="padding-left: 2rem;">
      {$form.not_before.html}
    </div>
  </div>
  <div>
    {$form.set_delay.immediate.html}
  </div>
  <div>
    {$form.set_delay.leave.html}
  </div>

  {* FOOTER *}
  <div class="crm-submit-buttons" style="margin-top:2rem;">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
  </div>
</div>
