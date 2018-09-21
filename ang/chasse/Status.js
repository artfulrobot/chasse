(function(angular, $, _) {

  angular.module('chasse').config(function($routeProvider) {
      $routeProvider.when('/chasse', {
        controller: 'ChasseStatus',
        templateUrl: '~/chasse/Status.html',

        // If you need to look up data when opening the page, list it out
        // under "resolve".
        resolve: {
          chasseStats: function(crmApi) {
            return crmApi('Chasse', 'getstats', {})
              .then(result => result.values);
          },
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
  angular.module('chasse').controller('ChasseStatus', function($scope, crmApi, crmStatus, crmUiHelp,
    chasseStats, chasseConfig, msgTpls, mailingGroups) {
    // The ts() and hs() functions help load strings for this module.
    var ts = $scope.ts = CRM.ts('chasse');
    var hs = $scope.hs = crmUiHelp({file: 'CRM/chasse/Status'}); // See: templates/CRM/chasse/Status.hlp

    $scope.stats = chasseStats;
    $scope.config = chasseConfig;
    var msg_tpl_lookup = {};
    for (i of msgTpls) msg_tpl_lookup[i.id] = i.msg_title;
    $scope.msg_tpls = msg_tpl_lookup;

    var groups = {};
    for (i of mailingGroups) groups[i.id] = i.title;
    $scope.groups = groups;

    $scope.busy = false;

    $scope.runJourney = function (journey_index) {
      $scope.busy = true;

      var r = {name: chasseConfig[journey_index].name};
      return crmStatus(
        // Status messages. For defaults, just use "{}"
        {start: ts('Processing Journey: %name...', r), success: ts('Finished processing journey: %name', r)},
        // The save action. Note that crmApi() returns a promise.
        crmApi('Chasse', 'step', { journey_index: journey_index })
      ).then(response => {
        $scope.busy = false;
        return crmApi('Chasse', 'getstats', {})
          .then(result => $scope.stats = result.values);
      });
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
        .then( result => $scope.stats = result.values )
      );
    };
  });

})(angular, CRM.$, CRM._);
