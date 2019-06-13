(function(angular, $, _) {

  angular.module('chasse').config(function($routeProvider) {
      $routeProvider.when('/chasse', {
        controller: 'ChasseStatus',
        templateUrl: '~/chasse/Status.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          chasseStats: function(crmApi) {
            return crmApi('Chasse', 'getstats', {});
          },
          chasseConfig: function(crmApi) {
            return crmApi('Setting', 'getvalue', { name: 'chasse_config' })
              .then( api_response => {
                if (Array.isArray(api_response.journeys)) {
                  api_response.journeys = {};
                }
                console.log('chasse_config', api_response);
                return api_response;
                })
          },
          mailingGroups: function(crmApi) {
            return crmApi('group', 'get', {
                "sequential": 1,
                "return": ["title","id"],
                "group_type": "Mailing List"})
            .then( response => response.is_error ? [] : response.values );
          },
          msgTpls: function(crmApi) {
            return crmApi('MessageTemplate', 'get', {
              "sequential": 1,
              'workflow_id' : {'IS NULL' : 1},
              'is_sms' : 0,
              'options' : {limit: 0},
              "return": ["id","msg_title"]
            })
            .then( response => response.is_error ? [] : response.values );
          }
        }
      });
    }
  );

  // The controller uses *injection*. This default injects a few things:
  //   $scope -- This is the set of variables shared between JS and HTML.
  //   crmApi, crmStatus, crmUiHelp -- These are services provided by civicrm-core.
  //   myContact -- The current contact, defined above in config().
  angular.module('chasse').controller('ChasseStatus', function($scope, $location, crmApi, crmStatus, crmUiHelp,
    chasseStats, chasseConfig, msgTpls, mailingGroups) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('chasse');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/chasse/Status'}); // See: templates/CRM/chasse/Status.hlp

    // We need to scale barcharts to a percentage based on the max
    // We also need to ensure each step has stats.

    function preProcessStats(result) {
      // Convert empty array to empty object.
      if (Array.isArray(result.values)) {
        result.values = {};
      }
      var max=1;
      for (var id of Object.keys(chasseConfig.journeys)) {
        var journey = chasseConfig.journeys[id];
        for (var i in journey.steps) {
          if (! (journey.steps[i].code in result.values)) {
            result.values[journey.steps[i].code] = { all:0, ready: 0 };
          }
          max = Math.max(max, result.values[journey.steps[i].code].all - result.values[journey.steps[i].code].ready, result.values[journey.steps[i].code].ready);
        }
      }
      $scope.maxContacts = max;
      $scope.stats = result.values;
    };
    // Initial procesing of stats.
    preProcessStats(chasseStats);

    $scope.noJourneys = Object.keys(chasseConfig.journeys).length === 0;

    $scope.config = chasseConfig;

    var msg_tpl_lookup = {};
    for (i of msgTpls) msg_tpl_lookup[i.id] = i.msg_title;
    $scope.msg_tpls = msg_tpl_lookup;

    var groups = {};
    for (i of mailingGroups) groups[i.id] = i.title;
    $scope.groups = groups;

    $scope.busy = false;

    /*
     * The step that is shown under the second step is the offset that is
     * stored against the first step.
     *
     * The first step (0) does cannot have a value.
     */
    $scope.prettifyInterval = function(journey, step_offset) {
      if (step_offset === 0) {
        return '';
      }
      if (!journey.steps[step_offset-1].interval) {
        return '';
      }
      var interval = journey.steps[step_offset-1].interval;
      var m = interval.match(/^(\d+ )(DAY|WEEK|MONTH)$/);
      if (!m) {
        return '';
      }
      return m[1] + m[2].toLowerCase() + ((m[1] == 1) ? '' : 's');
    };
    /**
     * The number of people on a step but can't be processed yet due to their
     * not_before date.
     */
    $scope.waiting = function waiting(step_code) {
      if (step_code in $scope.stats) {
        return $scope.stats[step_code].all - $scope.stats[step_code].ready;
      }
      else {
        console.log("can't find step code ", step_code, " in stats ", $scope.stats);
        return 0;
      }
    };
    /**
     * Does the previous step have an offset configured?
     */
    $scope.previousStepHasInterval = function previousStepHasInterval(journey, step_offset) {
      return (step_offset > 0 && journey.steps[step_offset-1].interval);
    }

    $scope.runJourney = function (journey_id) {
      $scope.busy = true;

      var r = {name: chasseConfig.journeys[journey_id].name};
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Processing Journey: %name...', r), success: ts('Finished processing journey: %name', r)},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Chasse', 'step', { journey_id: journey_id })
      ).then(response => {
        $scope.busy = false;
        return crmApi('Chasse', 'getstats', {})
          .then(preProcessStats);
      });
    };
    $scope.journeyHasSchedule = function journeyHasSchedule(journey) {
      return 'schedule' in journey;
    }
    $scope.describeSchedule = function describeSchedule(schedule) {
      var s = '';
      if ('days' in schedule) {
        var days=[];
        for (var i=0;i<schedule.days.length;i++) {
          days.push(['', 'Mon', 'Tues', 'Wednes', 'Thurs', 'Fri', 'Satur', 'Sun'][schedule.days[i]] + 'days');
        }
        s += ' on ' + days.join(', ');
      }
      if (schedule.day_of_month) {
        s += ' on ' + schedule.day_of_month + th(schedule.day_of_month) + ' of each month';
      }
      if (schedule.time_earliest) {
        s += ' after ' + schedule.time_earliest;
      }
      if (schedule.time_latest) {
        s += ' before ' + schedule.time_latest;
      }
      return s;
    }
    function th(dom) {
      if (!dom) {
        return '';
      }
      const last_digit = dom.substr(-1);
      if (last_digit === '1' && dom != '11') {
        return 'st';
      }
      if (last_digit === '2' && dom != '12') {
        return 'nd';
      }
      if (last_digit === '3') {
        return 'rd';
      }
      return 'th';
    };
    $scope.save = function save() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Contact', 'create', {
          id: myContact.id,
          first_name: myContact.first_name,
          last_name: myContact.last_name
        })
      );
    };
    $scope.reload = function () {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Loading...'), success: ts('Loaded')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Chasse', 'getstats', {})
          .then(preProcessStats)
        );
    };
  });

})(angular, CRM.$, CRM._);
