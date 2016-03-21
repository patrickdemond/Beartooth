define( function() {
  'use strict';

  try { var module = cenozoApp.module( 'setting', true ); } catch( err ) { console.warn( err ); return; }
  angular.extend( module, {
    identifier: {
      parent: {
        subject: 'site',
        column: 'site_id',
        friendly: 'site'
      }
    },
    name: {
      singular: 'setting',
      plural: 'settings',
      possessive: 'setting\'s',
      pluralPossessive: 'settings\''
    },
    columnList: {
      site: {
        column: 'site.name',
        title: 'Site'
      },
      call_without_webphone: {
        title: 'No-Webphone',
        type: 'boolean',
        help: 'Allow users to make calls without being connected to the webphone'
      },
      calling_start_time: {
        title: 'Start Call',
        type: 'time_notz',
        help: 'The earliest time to assign participants (in their local time)'
      },
      calling_end_time: {
        title: 'End Call',
        type: 'time_notz',
        help: 'The latest time to assign participants (in their local time)'
      },
      appointment_update_span: {
        title: 'Update Span',
        type: 'number',
        help: 'How many days into the future to include appointments when fetching the appointment list'
      },
      appointment_home_duration: {
        title: 'Home Ap.',
        type: 'number',
        help: 'The length of time, in minutes, that a home appointment is estimated to take'
      },
      appointment_site_duration: {
        title: 'Site Ap.',
        type: 'number',
        help: 'The length of time, in minutes, that a site appointment is estimated to take'
      },
      pre_call_window: {
        title: 'Pre-Call',
        type: 'number',
        help: 'How many minutes before an appointment or callback that a participant can be assigned'
      }
    },
    defaultOrder: {
      column: 'site',
      reverse: false
    }
  } );

  module.addInputGroup( null, {
    site: {
      column: 'site.name',
      title: 'Site',
      type: 'string',
      constant: true
    },
    call_without_webphone: {
      title: 'Allow calls without a webphone',
      type: 'boolean',
      help: 'Allow users to make calls without being connected to the webphone'
    },
    calling_start_time: {
      title: 'Earliest Call Time',
      type: 'time_notz',
      help: 'The earliest time to assign participants (in their local time)'
    },
    calling_end_time: {
      title: 'Latest Call Time',
      type: 'time_notz',
      help: 'The latest time to assign participants (in their local time)'
    },
    appointment_update_span: {
      title: 'Appointment Update Span',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many days into the future to include appointments when fetching the appointment list'
    },
    appointment_home_duration: {
      title: 'Home Appointment Duration',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'The length of time, in minutes, that a home appointment is estimated to take'
    },
    appointment_site_duration: {
      title: 'Site Appointment Duration',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'The length of time, in minutes, that a site appointment is estimated to take'
    },
    pre_call_window: {
      title: 'Pre-Appointment Window',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes before an appointment or callback that a participant can be assigned'
    }
  } );

  module.addInputGroup( 'Last Call Wait Times', {
    contacted_wait: {
      title: 'Contacted Wait',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes after a "contacted" call result to allow a participant to be called'
    },
    busy_wait: {
      title: 'Busy Wait',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes after a "busy" call result to allow a participant to be called'
    },
    fax_wait: {
      title: 'Fax Wait',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes after a "fax" call result to allow a participant to be called'
    },
    no_answer_wait: {
      title: 'No Answer Wait',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes after a "no answer" call result to allow a participant to be called'
    },
    not_reached_wait: {
      title: 'Not Reached Wait',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes after a "not reached" call result to allow a participant to be called'
    },
    hang_up_wait: {
      title: 'Hang Up Wait',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes after a "hang up" call result to allow a participant to be called'
    },
    soft_refusal_wait: {
      title: 'Soft Refusal Wait',
      type: 'string',
      format: 'integer',
      minValue: 0,
      help: 'How many minutes after a "soft refusal" call result to allow a participant to be called'
    }
  } );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnSettingList', [
    'CnSettingModelFactory',
    function( CnSettingModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'list.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnSettingModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.directive( 'cnSettingView', [
    'CnSettingModelFactory',
    function( CnSettingModelFactory ) {
      return {
        templateUrl: module.getFileUrl( 'view.tpl.html' ),
        restrict: 'E',
        scope: { model: '=?' },
        controller: function( $scope ) {
          if( angular.isUndefined( $scope.model ) ) $scope.model = CnSettingModelFactory.root;
        }
      };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnSettingListFactory', [
    'CnBaseListFactory',
    function( CnBaseListFactory ) {
      var object = function( parentModel ) { CnBaseListFactory.construct( this, parentModel ); };
      return { instance: function( parentModel ) { return new object( parentModel ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnSettingViewFactory', [
    'CnBaseViewFactory',
    function( CnBaseViewFactory ) {
      var args = arguments;
      var CnBaseViewFactory = args[0];
      var object = function( parentModel, root ) { CnBaseViewFactory.construct( this, parentModel, root ); }
      return { instance: function( parentModel, root ) { return new object( parentModel, root ); } };
    }
  ] );

  /* ######################################################################################################## */
  cenozo.providers.factory( 'CnSettingModelFactory', [
    '$state', 'CnBaseModelFactory', 'CnSettingListFactory', 'CnSettingViewFactory',
    function( $state, CnBaseModelFactory, CnSettingListFactory, CnSettingViewFactory ) {
      var object = function( root ) {
        var self = this;
        CnBaseModelFactory.construct( this, module );
        this.listModel = CnSettingListFactory.instance( this );
        this.viewModel = CnSettingViewFactory.instance( this, root );
      };

      return {
        root: new object( true ),
        instance: function() { return new object( false ); }
      };
    }
  ] );

} );
