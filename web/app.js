'use strict';

var cenozo = angular.module( 'cenozo' );

cenozo.controller( 'HeaderCtrl', [
  '$scope', '$state', 'CnBaseHeader', 'CnSession', 'CnHttpFactory', 'CnModalMessageFactory',
  function( $scope, $state, CnBaseHeader, CnSession, CnHttpFactory, CnModalMessageFactory ) {
    // copy all properties from the base header
    CnBaseHeader.construct( $scope );

    // add custom operations here by adding a new property to $scope.operationList

    CnSession.promise.then( function() {
      var assignmentType = CnSession.user.assignmentType;
      CnSession.alertHeader = 'none' == assignmentType
                            ? undefined
                            : 'You are currently in a ' + assignmentType + ' assignment';
      CnSession.onAlertHeader = function() {
        if( angular.isDefined( cenozoApp.module( 'assignment' ).actions.control ) ) {
          $state.go( 'assignment.control', { type: assignmentType } );
        } else {
          CnModalMessageFactory.instance( {
            title: 'Switch Roles For ' + assignmentType.ucWords() + ' Assignment',
            message:
              'You cannot access your assignment under your current site and role. ' +
              'The site and role selection dialog will now be opened, please use it to switch to the site and ' +
              'role under which you started the assignment.\n\n' +
              'Once you have switched you will be able to access your assignment.',
            error: true
          } ).show().then( function() {
            CnSession.showSiteRoleModal();
          } );
        }
      };
    } );

    // don't allow users to log out if they have an active assignment
    var logoutOperation = $scope.operationList.findByProperty( 'title', 'Logout' );
    var logoutFunction = logoutOperation.execute;
    logoutOperation.execute = function() {
      // private function to redirect the user to the assignment control
      function showAssignmentExists( assignmentType ) {
        var hasAccess = angular.isDefined( cenozoApp.module( 'assignment' ).actions.control );

        CnModalMessageFactory.instance( {
          title: 'Active ' + assignmentType.ucWords() + ' Assignment Detected',
          message: 'You cannot log out while in an open assignment!\n\n' + ( hasAccess
            ? 'In order to log out you will need to close your open assignment. ' +
              'You will now be redirected to your assignment.'
            : 'In order to log out you will need to close your open assignment, however, you cannot access ' +
              'your assignment from your current site and role. ' +
              'The site and role selection dialog will now be opened, please use it to switch to the site and ' +
              'role under which you started the assignment.\n\n' +
              'Once you have switched you will be able to access your assignment.'
          ),
          error: true
        } ).show().then( function() {
          // check if the role has access to the assignment module
          if( hasAccess ) $state.go( 'assignment.control', { type: assignmentType } );
          else CnSession.showSiteRoleModal();
        } );
      }

      CnHttpFactory.instance( {
        path: 'assignment/0',
        data: { select: { column: [ { table: 'qnaire', column: 'type' } ] } },
        onError: function( response ) {
          if( 307 == response.status ) {
            // 307 means the user has no active assignment
            logoutFunction();
          } else if( 403 == response.status ) {
            // 403 means there is an assignment, but under a different site
            showAssignmentExists();
          } else { CnModalMessageFactory.httpError( response ); }
        }
      } ).get().then( function( response ) {
        // active assignment detected
        showAssignmentExists( response.data.type );
      } );
    };
  }
] );
