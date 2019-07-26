# Developers' information

This page details info for developers or those who want to work with it
programmatically.

## Configuration


    {
      next_id    : 124,
      journeys   : {
        journey1    : <journey_def>,
        journey123  : <journey_def>,
        <journeyid> : <journey_def>,
        ...
      },
    }

- `next_id` ensures new journeys have unique ids, e.g. here the next journey id
  will be `journey124`

- `journeys` is an array of journey definitions (see below), keyed by journey id

A `<journeydef>` looks like:

     'name'          : 'Admin name of journey',
     'id'            : 'journey1',
     'mailing_group' : 123,
     'mail_from'     : <from_id>,
     'schedule'      : <schedule>,
     'steps'         : [ <stepdef>, <stepdef>, ... ]

If `schedule` is not present, then this journey will NOT be run by the
scheduler. i.e. original functionality.

A `<schedule>` is an array of constraints, keyed by the type of constraint. Only
the relevant constraints should be present. An empty array means run every time.

    {
        'days'          : [1, 3, 7], // ISO-8601 1=Monday, 7=Sunday.
        'day_of_month'  : 1,
        'time_earliest' : '09:00', // 24 hr format.
        'time_latest'   : '23:00',
    }


A `stepdef` looks like:

    {
        'code' : 'S1',
        'next_code' : 'S2',
        'send_mailing' : $this->msg_tpl_1,
        'add_to_group' : '',
        'interval' : <interval>
    },

- `code` is a unique code used by admins. Nb. it must be unique across all
  journeys.

- `next_code` is what to set a contact's step field to after processing this
  step. It's either blank (no next step, end of journey) or the code of the
  following step.

- `send_mailing` is the message template ID of the mailing to send, or blank for
  not to send a mailing.

- `add_to_group` if truthy ('1') then after processing this step contacts are
  added to the group that is specified in the journey's config.

- `<interval>` is missing, blank, or an SQL valid interval like "7 DAY".

## Release notes

### v2

Breaking changes:

If you had any code that called the Chasse.step API using `journey_index` you
now need to update this to use `journey_id`. Journey indexes were problematic -
delete journey 0 and now journey 0 would be what used to be journey 1.

After initial upgrade journey id will be 'journeyN' where N is the old journey
index. But after changing journeys there may be different numbers so look them
up before writing them into scripts.

