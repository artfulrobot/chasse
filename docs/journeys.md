# Configure your journeys

This page explains how to configure the journeys that contacts can move along.

We'll take the example of a journey used to introduce your organisation to
people who want to sign up to your email newsletter. Instead of just adding the
contact straight onto your newsletter with everyone else, you want them to
receive a welcome message, then a week later get another email explaining how
cool your organisation is, and then a bit later another that explains how they
can help, and another a week after that asking for a small donation. Finally
you'll just put them on the newsletter group with everyone else.

## Create your first journey

Find **Mailings » Chassé Supporter Journeys** in the menus, then click the **Add
new journey** button.

Give your journey a name, e.g. "Initial onboarding journey", "Newsletter
welcome" or similar.

Each journey uses a Mailing Group (i.e. a CiviCRM group that has the Mailing
Group box ticked in its settings). This group is used throughout the journey as
the Unsubscribe Group; that is if anyone unsubscribes during the journey, it's
that group that they unsubscribe from. Here you would select your newsletter
mailing group.

If a step has the "Add to group" option checked, then when that step is
processed the contacts will be added into that group. e.g. this is useful for
our newsletter onboarding example - the last step should add them onto the
general newsletter group.

Leave the *Processing* option un-checked for now, we'll come back to that later.

## Add steps to your journey

Each step needs a code. For example if you have a subscriber journey you might
choose to name your steps S1, S2, S3, S4... or Subscriber1, Subscriber2... It
doesn't matter as long as the code is unique among all the steps in all the
journeys. For this example let's use S1.

On our first step (S1) we want to send them the initial "Hi, thanks for signing up"
email, so we select that form the **Send Mailing** drop down which lists all
your available message templates.

!!! tip

    Note that you configure message templates at: **Mailings » Message Templates**.

    If you want to design your emails using Mosaico you will need to use the
    [Mosaico Message Tempate Synchronization](https://github.com/civicrm/org.civicrm.mosaicomsgtpl)
    extension.

You'll need to select which From address is used for each step's mailing.

After they receive the welcome mailing we want to delay processing the next
step for a week, so select *1 Week* for the "Delay next step by".

Continue to add more steps in a similar way.

The last step is different: we might want to send a mailing but we also want to
add them to the group, so we tick that box. As there isn't a step after this we
don't need a delay next step.

Once you're done, click **Save** to save your journey.

## Processing options

We want people on our journey to move along each week, starting one week after
they join. So if someone starts on a Monday, we expect their next mailing to
also be on a Monday, whereas someone starting on a Tuesday would always be on a
Tuesday.

As we've set delays between each step, we can tick the **Use Automatic
Schedule** option to achieve this.

The "Run every" weekday options limits when the processor runs to those days. If
we don't tick any of the days there's no limit; it's the same as ticking them
all. Note that if you select -say- Wednesday, Friday people will only ever get
mailed on Wednesdays of Fridays; the step delays you entered work as "at least"
values. So if your step delay was 3 days, someone who started on Wednesday would
not receive a mailing on Friday (not 3 days yet) and so would receive their next
one on the following Wednesday. But someone who started on Friday would get
their next mailing on Wednesday, since their 3 day wait is up by then.

Anyway, for this example, we're happy for our mailings to go out any day, so we
just leave those blank.

Likewise we don't want to limit our mailings to a day of the month so we leave
that blank, too.

The two "Don't run before/after" settings take times. This is useful if you want
to target your mailings within waking hours, for example. Or if you think people
are more receiptive in the evenings, you could specify that.

!!! warning "Watch out!"

    If you don't have delays set between steps then people will progress along
    every time the journey is processed. If you have selected Automatic
    Processing then the journey is processed every time your site's "cron" runs,
    typically every hour but can be every 5 minutes - ask your site
    administrator. So if your steps don't have delays someone could get all the
    emails in the space of half an hour which would almost definitely annoy
    them.

Also note that if your site's cron job does not run within the window you have
configured for your journeys; your journeys will never progress. e.g. if your
cron runs on the hour, but you specified "Don't run before 9:10" and "Don't run
after 9:30" then it would never trigger!

### Non automatic processing

You may prefer to trigger processing by another means, in which case simply
un-check the Automtic Processing option.

You can manually progress a journey from the status screen (there's a button for
it), or you can do your own automation using the API and a custom cron job or a
custom Scheduled Job.

### Chasse.step API action

This API provides a way to programmatically progress journeys.

- Chasse.step - progress *all* journeys

- Chasse.step journey_id=journey3 - just the journey identified by journey3

- Chasse.step journey_id=journey2 step=CD4  - But the only reason to do a single
  step is for testing.
