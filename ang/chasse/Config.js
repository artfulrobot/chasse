(function(angular, $, _) {

  angular.module('chasse').config(function($routeProvider) {
      $routeProvider.when('/chasse/config/:id', {
        controller: 'ChasseConfig',
        templateUrl: '~/chasse/Config.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          chasseConfig: function(crmApi) {
            return crmApi('Setting', 'getvalue', { name: 'chasse_config' })
              .then( api_response => {
                // We need the journeys item to be an object, not an array, but in the
                // conversion from PHP's empty associative array to json, an empty object
                // will be received as an empty array. So we need to get rid of that now
                // for that case.

                if (Array.isArray(api_response.journeys)) {
                  api_response.journeys = {};
                }
                console.log('chasse_config', api_response);
                return api_response;
              });
          },
          mailingGroups: function(crmApi) {
            return crmApi('group', 'get', {
                "sequential": 1,
                "return": ["title","id"],
                "options": {"limit":0},
                'is_hidden' : 0,
                "group_type": "Mailing List"})
            .then( response => response.is_error ? [] : response.values );
          },
	  mailingCampaigns: function(crmApi) {
	   return crmApi('Setting', 'getvalue', {
		"name": "enable_components" })
	   .then ( enabled_components => {
		if (Object.values(enabled_components).indexOf("CiviCampaign") === -1) { 
			return [];
		 } else {
			return crmApi('campaign', 'get', {
       					"sequential": 1,
                			"return": ["title","id"],
                			"options": {"limit":0},
					"is_active": 1})
            			.then( response => response.is_error ? [] : response.values );
		}
	    });
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
          },
          mailFroms: function(crmApi) {
            return crmApi('OptionValue', 'get', {
              'options' : {limit: 0},
              'sequential' : 1,
              'option_group_id' : "from_email_address",
              "return": ["label", "value"]
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
  angular.module('chasse').controller('ChasseConfig', function($route, $scope, $location, crmApi, crmStatus, crmUiHelp,
    chasseConfig, mailingGroups, mailingCampaigns, msgTpls, mailFroms) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('chasse');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/chasse/Config'}); // See: templates/CRM/chasse/Config.hlp

    // We have myContact available in JS. We also want to reference it in HTML.
    console.log("in controller chasseConfig is", chasseConfig);
    const orig = CRM._.clone(chasseConfig, true);
    $scope.dirty = false;
    $scope.setDirty = function() {console.log("setDirty"); $scope.dirty = true;};
    $scope.config = chasseConfig;
    $scope.groups = mailingGroups;
    $scope.campaigns = mailingCampaigns;
    $scope.display_campaign_options = !(mailingCampaigns.length === 0);
    $scope.msg_tpls = msgTpls;
    $scope.mail_froms = mailFroms;

    $scope.addJourney = function addJourney() {
      $scope.dirty = true;
      var new_id = 'journey' + chasseConfig.next_id;
      chasseConfig.next_id = parseInt(chasseConfig.next_id) + 1;

      chasseConfig.journeys[new_id] = {
        name: 'Untitled Journey',
        id: new_id,
        steps: [],
      };
      $scope.journey = chasseConfig.journeys[new_id];
      $scope.id = new_id;
    };
    if ($route.current.params.id === 'new') {
      $scope.addJourney();
    }
    else {
      $scope.journey = chasseConfig.journeys[$route.current.params.id];
      $scope.id = $route.current.params.id;
    }

    $scope.deleteJourney = function (id) {
      $scope.dirty = true;
      var journey = chasseConfig.journeys[id];
      if (confirm("Are you sure you want to delete journey called " + journey.name + "?")) {
        delete chasseConfig.journeys[id];
      }
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Deleting...'), success: ts('Deleted.')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Setting', 'create', { 'chasse_config': chasseConfig })
      ).then( () => {
        // Go back to main page.
        $location.path('/chasse');
      });
    };

    /**
     * Enforce the step sequence in the config.
     *
     * Nb. storing the next_code in the step item when it always just points to
     * the next step is redundancy.
     *
     * The reason for it is that originally it was envisaged that people might
     * want journeys to be non-linear; have branches etc. However the UI
     * improvements/simplifications in v2 remove this possibility - it's an
     * edge use-case anyway so better to keep the 90% of users who won't want
     * it happy. But I've not removed the next_step code from config so that if
     * we want to enhance the UI too allow non-linear journeys in future, we
     * can. And therefore we have this fix...
     *
     * Nb. this is only performed on Save. Ideally it would be called as a
     * watch expression...
     */
    $scope.redoNextSteps = function(steps) {
      for (var i=0; i<steps.length - 1; i++) {
        steps[i].next_code = steps[i+1].code;
      }
      if (steps.length > 0) {
        steps[steps.length-1].next_code = '';
      }
    };

    $scope.moveStep = function moveStep(journey, step_old, step_new) {
      $scope.dirty = true;
      var tmp = journey.steps.splice(step_old,1)[0];
      journey.steps.splice(step_new, 0, tmp);
      $scope.redoNextSteps(journey.steps);
    };
    $scope.deleteStep = function deleteStep(journey, step_idx) {
      if (confirm("Delete this step, sure?")) {
        $scope.dirty = true;
        journey.steps.splice(step_idx, 1);
        $scope.redoNextSteps(journey.steps);
      }
    };
    $scope.addStep = function addStep(journey) {
      $scope.dirty = true;
      console.log("journey: ", journey);
      if (! journey.steps) {
        journey.steps = [];
      }
      journey.steps.push({
        code: '',
        next_code: '',
        send_mailing: '',
        add_to_group: false,
      });
    };

    $scope.save = function save() {
      // Redo journey steps, just in case.
      for (var id of Object.keys(chasseConfig.journeys)) {
        $scope.redoNextSteps(chasseConfig.journeys[id].steps);
      }

      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Setting', 'create', { 'chasse_config': chasseConfig })
      ).then( () => {
        // Go back to status page after a save.
        $location.path('/chasse');
        /*
        if ($location.path() === '/chasse/config/new') {
          console.log("changing to " + $scope.id);
          $location.path("/chasse/config/" + $scope.id);
        }
        else {
          $scope.dirty=false;
        }
        */
      });
    };
    $scope.stepCodeIsValid = function stepCodeIsValid(journey_id, step_offset, code) {
      var lowercaseCode = code.toLowerCase();
      for (var id of Object.keys(chasseConfig.journeys)) {
        for (var i=0; i<chasseConfig.journeys[id].steps.length; i++) {
          //console.log(journey_id + '.' + step_offset + '.' + lowercaseCode, "<>", id+'.'+i+chasseConfig.journeys[id].steps[i].code.toLowerCase() );
          if (chasseConfig.journeys[id].steps[i].code.toLowerCase() === lowercaseCode
            && !(journey_id === id && step_offset === i)) {
              return false;
          }
        }
      }
      return true;
    };

    if (Object.keys(chasseConfig.journeys).length === 0) {
      $scope.addJourney();
    }
  })
  .directive('intervalSelector', function() {
    return {
      restrict: 'E', // only <interval-selector/>
      scope: {
        string: '=interval',
        setDirty: '&' // This means it's an event that the directive can fire.
      },
      templateUrl: '~/chasse/intervalSelector.html',
      controller: ['$scope', function intervalSelectorController($scope) {
        if (typeof($scope.string) === 'undefined') {
          $scope.string = '';
        }
        var m = $scope.string.match(/^(\d+) (DAY|WEEK|MONTH)$/);
        if (m) {
          $scope.qty = m[1];
          $scope.unit = m[2];
        }
        else {
          $scope.qty = 1; // sensible default.
          $scope.unit = 'time';
        }

        $scope.updateString = function() {
          if ($scope.unit === 'time') {
            $scope.string = '';
          }
          else {
            $scope.string = $scope.qty + ' ' + $scope.unit;
          }
          $scope.setDirty({ev: {}});
        };
      }]
    };
  })
  .directive('scheduleEditor', function() {
    return {
      restrict: 'E', // only <schedule-editor/>
      scope: {
        journey: '=journey',
        setDirty: '&' // This means it's an event that the directive can fire.
      },
      templateUrl: '~/chasse/scheduleEditor.html',
      controller: ['$scope', function scheduleEditorController($scope) {

        var schedule = $scope.journey.schedule;

        // Set up empty vars.
        $scope.d = {
          useSchedule  : false,
          days         : [0, 0, 0, 0, 0, 0, 0],
          dayOfMonth   : '',
          timeEarliest : '',
          timeLatest   : '',
        };

        // Unpack the schedule
        if (typeof(schedule) !== 'undefined') {
          console.log("unpack", schedule);
          $scope.d.useSchedule = true;
          // There is a schedule defined, parse it.
          if ('days' in schedule) {
            for (var i=0; i<7; i++) {
              $scope.d.days[i] = schedule.days.indexOf((i+1) + '') > -1;
            }
          }
          if ('day_of_month' in schedule) {
            $scope.d.dayOfMonth = schedule.day_of_month;
          }
          if ('time_earliest' in schedule) {
            $scope.d.timeEarliest = schedule.time_earliest;
          }
          if ('time_latest' in schedule) {
            $scope.d.timeLatest = schedule.time_latest;
          }
        }

        // Repack the schedule.
        $scope.updateSchedule = function() {
          if (!$scope.d.useSchedule) {
            // Remove schedule.
            delete($scope.journey.schedule);
          }
          else {
            // Create schedule from scratch.
            $scope.journey.schedule = {};

            // Week days.
            var days = [];
            for (var i=0; i<7; i++) {
              if ($scope.d.days[i]) {
                days.push(i+1);
              }
            }
            if (days.length) {
              $scope.journey.schedule.days = days;
            }
            if ($scope.d.dayOfMonth) {
              $scope.journey.schedule.day_of_month = $scope.d.dayOfMonth;
            }
            if ($scope.d.timeEarliest) {
              $scope.journey.schedule.time_earliest = $scope.d.timeEarliest;
            }
            if ($scope.d.timeLatest) {
              $scope.journey.schedule.time_latest = $scope.d.timeLatest;
            }
          }
          $scope.setDirty({ev: {}});
        };

        $scope.th = function() {
          if (!$scope.d.dayOfMonth) {
            return '';
          }
          const dom = $scope.d.dayOfMonth;
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
        $scope.validTime = function(t) {
          if (!t) return true;
          if (t.match(/^[012]?[0-9]:[0-5][0-9]$/)) {
            return true;
          }
          console.log("invalid ", t);
          return false;
        };

      }]
    };
  })
  ;

})(angular, CRM.$, CRM._);
