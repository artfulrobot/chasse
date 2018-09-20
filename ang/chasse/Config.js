(function(angular, $, _) {

  angular.module('chasse').config(function($routeProvider) {
      $routeProvider.when('/chasse/config', {
        controller: 'ChasseConfig',
        templateUrl: '~/chasse/Config.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          chasseConfig: function(crmApi) {
            return crmApi('Setting', 'getvalue', { name: 'chasse_config' })
              .then( api_response => {
                console.log(api_response);
                return CRM._.isArray(api_response) ? api_response : [] ;
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
  angular.module('chasse').controller('ChasseConfig', function($scope, crmApi, crmStatus, crmUiHelp, chasseConfig, mailingGroups, msgTpls) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('chasse');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/chasse/Config'}); // See: templates/CRM/chasse/Config.hlp

    // We have myContact available in JS. We also want to reference it in HTML.
    console.log("in controller chasseConfig is", chasseConfig);
    console.log("msgTpls", msgTpls);
    const orig = CRM._.clone(chasseConfig, true);
    $scope.dirty = false;
    $scope.setDirty = function() { $scope.dirty = true; console.log("XXXXXXXXXXXXXXXX")};
    $scope.config = chasseConfig;
    $scope.groups = mailingGroups;
    $scope.msg_tpls = msgTpls;

    $scope.addJourney = function addJourney() {
      $scope.dirty = true;
      if (CRM._.isArray(chasseConfig)) {
      chasseConfig.push({
        name: 'Untitled Journey',
        steps: [],
      });}
    };
    $scope.deleteJourney = function (i) {
      $scope.dirty = true;
      var journey = chasseConfig[i];
      if (confirm("Are you sure you want to delete journey called " + journey.name + "?")) {
        chasseConfig.splice(i,1);
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
        send_mailing: true,
        add_to_group: false,
      });
    };

    $scope.save = function save() {
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Saving...'), success: ts('Saved')},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Setting', 'create', { 'chasse_config': chasseConfig })
      ).then( () => $scope.dirty=false );
    };
  });

})(angular, CRM.$, CRM._);
