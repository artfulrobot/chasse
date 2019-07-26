# Chassé - Email supporter journeys


Get your supporters [chassé](https://en.wikipedia.org/wiki/Chass%C3%A9)-ing
through an email-driven donor journey to improve your organisation's onboarding
experience and improve supporter development.

![Screenshot of status screen](./chasse-overview.png)

The concept is that each contact can be at a particular stage of one of a set of
journeys. So you might have a journey for new people to your email list, and a
journey for first time donors and a journey for regular givers.

A "journey" is here simply means they'll get sent a series of emails as they
"step" forward. Or <em>chassé</em> across!

The system can be **automated** so that people bump along without you clicking anything.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

1. Get it [installed](/install).

2. [Configure your journeys](/journeys).

3. [Put people on a journey](./kickoff.md).to get them started.


## Example use case

**Hint**: You should find a link to **Chassé Supporter Journeys** under the
**Mailings** menu, once this is installed.

When people sign up for our email newsletter, instead of just chucking them on
the list with everyone else, we want to send them a particular email each week,
so we can welcome them, introduce what we do, and finally, give them the
opportunity to make a cash donation. At the end of these 3 weeks the contact is
moved to the main newsletter group and receives that along with everyone else.

If they do give a cash donation, we want put them on a new 4 week journey, at
the end of which we'll ask them for a regular gift. Again, if they don't take up
that opportunity they'll just go onto the newsletter list as normal.

Of course people may choose to unsubscribe at any time and we must stop their
journey right away if that happens.

We'll give codes to all the stages: S1..S3 for the **s**ubscriber journey,
CD1..CD4 for the **c**ash **d**onor journey. And we set up message templates for
each of the steps.

We put these plans into the extension:

![Screenshot](./images/screenshot-plan.png)

We created 2 journeys. Each journey has:

- a name (just for your reference)

- a mailing group (used as the "unsubscribe group" for mailings)

- a From email address

And then steps in that journey:

1. We assume that some other online system has plonked the new would-be subscriber in our CRM, sent them a thanks/confirmation and set their journey status to S1. So the S1 step simply says that the next step should be S2.

2. Step S2 says that we should send a particular mailing, then move them up to step S3

3. Step S3 says send the mailing, then add them to the mailing group, and that's the end of the journey, so the next code is blank.

The second journey is basically the same but with another step.

The status page shows it more succinctly:

![Screenshot](./images/screenshot-status.png)

## Processing along

You can hit the Process Now button to force a process along, but normally you would use a **weekly Scheduled Job** to trigger the process. You can process a single step, a single journey, or all journeys.

The API calls to do this are:

- Chasse.step - all journeys

- Chasse.step journey=1 - just the 2nd journey (journeys are zero indexed, so 0 is the first one)

- Chasse.step journey=1 step=CD4  - But the only reason to do a single step is for testing.

You can see the results of the processing in the usual places:

- The Sent/Scheduled mailings screen.

- The contact records - the groups tab; the activities tab; and the new journey step field:   
  ![screenshot](./images/screenshot-custom-field.png)

## Unsubscribing

In CiviCRM, a contact can be in one of three states in relation to a group:

1. subscribed

2. removed

3. neither subscribed nor removed.

This extension assumes it's OK to mail people on a journey unless they're **removed** from the configured mailing group. So you obviously need to take care with your entry-point systems that put people on the journey to begin with that you have consent to do this (suitable to the law of your land, e.g. GDPR).

When someone unsubscribes from the mailing group used by one of these mailings, if they have a journey status that belongs to a journey that uses that mailing group, their journey step field is set empty. Because of this, they won't ever get to the "add to group" last step in our example, so they won't get added back in.






