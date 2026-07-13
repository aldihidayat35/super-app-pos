<!doctype html>
<!--
Author: Keenthemes
Product Name: MetronicProduct Version: 8.3.3
Purchase: https://1.envato.market/EA4JP
Website: http://www.keenthemes.com
Contact: support@keenthemes.com
Follow: www.twitter.com/keenthemes
Dribbble: www.dribbble.com/keenthemes
Like: www.facebook.com/keenthemes
License: For each use you must have a valid license purchased only from above link in order to legally use the theme for your project.
-->
<html lang="en">
  <!--begin::Head-->
  <head>
    <base href="" />
    <title>
      Metronic - The World's #1 Selling Tailwind CSS & Bootstrap Admin Template
      by KeenThemes
    </title>
    <meta charset="utf-8" />
    <meta
      name="description"
      content="
            The most advanced Tailwind CSS & Bootstrap 5 Admin Theme with 40 unique prebuilt layouts on Themeforest trusted by 100,000 beginners and professionals. Multi-demo,
            Dark Mode, RTL support and complete React, Angular, Vue, Asp.Net Core, Rails, Spring, Blazor, Django, Express.js, Node.js, Flask, Symfony & Laravel versions.
            Grab your copy now and get life-time updates for free.
        "
    />
    <meta
      name="keywords"
      content="
            tailwind, tailwindcss, metronic, bootstrap, bootstrap 5, angular, VueJs, React, Asp.Net Core, Rails, Spring, Blazor, Django, Express.js,
            Node.js, Flask, Symfony & Laravel starter kits, admin themes, web design, figma, web development, free templates,
            free admin themes, bootstrap theme, bootstrap template, bootstrap dashboard, bootstrap dak mode, bootstrap button,
            bootstrap datepicker, bootstrap timepicker, fullcalendar, datatables, flaticon
        "
    />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta property="og:locale" content="en_US" />
    <meta property="og:type" content="article" />
    <meta
      property="og:title"
      content="Metronic - The World's #1 Selling Tailwind CSS & Bootstrap Admin Template by KeenThemes"
    />
    <meta property="og:url" content="https://keenthemes.com/metronic" />
    <meta property="og:site_name" content="Metronic by Keenthemes" />
    <link
      rel="canonical"
      href="http://preview.keenthemes.com/metronic8/demo38/?page=index"
    />
    <link
      rel="shortcut icon"
      href="/metronic8/demo38/assets/media/logos/favicon.ico"
    />
    <!--begin::Fonts(mandatory for all pages)-->
    <link
      rel="stylesheet"
      href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700"
    />
    <!--end::Fonts-->
    <!--begin::Vendor Stylesheets(used for this page only)-->
    <link
      href="/metronic8/demo38/assets/plugins/custom/fullcalendar/fullcalendar.bundle.css"
      rel="stylesheet"
      type="text/css"
    />
    <!--end::Vendor Stylesheets-->

    <!--begin::Global Stylesheets Bundle(mandatory for all pages)-->
    <link
      href="/metronic8/demo38/assets/plugins/global/plugins.bundle.css"
      rel="stylesheet"
      type="text/css"
    />
    <link
      href="/metronic8/demo38/assets/css/style.bundle.css"
      rel="stylesheet"
      type="text/css"
    />
    <!--end::Global Stylesheets Bundle-->

    @include('partials/trackers/_ga-tag-manager-for-head')

    <script>
      // Frame-busting to prevent site from being loaded within a frame without permission (click-jacking)
      if (window.top != window.self) {
        window.top.location.replace(window.self.location.href);
      }
    </script>
  </head>
  <!--end::Head-->
  <!--begin::Body-->
  <body
    id="kt_app_body"
    data-kt-app-header-fixed="true"
    data-kt-app-header-fixed-mobile="true"
    data-kt-app-sidebar-enabled="true"
    data-kt-app-sidebar-fixed="true"
    data-kt-app-sidebar-hoverable="true"
    data-kt-app-sidebar-push-header="true"
    data-kt-app-sidebar-push-toolbar="true"
    data-kt-app-sidebar-push-footer="true"
    class="app-default"
  >
    @include('partials/theme-mode/_init')
    @include('partials/trackers/_ga-tag-manager-for-body')
    @include('layout/_default') @include('partials/engage-new/_main')
    @include('partials/_scrolltop')
    <!--begin::Modals-->

    @include('partials/modals/_upgrade-plan')
    @include('partials/modals/_view-users')
    <!--end::Modals-->

    <!--begin::Javascript-->
    <script>
      var hostUrl = "/metronic8/demo38/assets/";
    </script>
    <!--begin::Global Javascript Bundle(mandatory for all pages)-->
    <script src="/metronic8/demo38/assets/plugins/global/plugins.bundle.js"></script>
    <script src="/metronic8/demo38/assets/js/scripts.bundle.js"></script>
    <!--end::Global Javascript Bundle-->

    <!--begin::Vendors Javascript(used for this page only)-->
    <script src="/metronic8/demo38/assets/plugins/custom/fullcalendar/fullcalendar.bundle.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/percent.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/radar.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/map.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/continentsLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/usaLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZonesLow.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/geodata/worldTimeZoneAreasLow.js"></script>
    <!--end::Vendors Javascript-->

    <!--begin::Custom Javascript(used for this page only)-->
    <script src="/metronic8/demo38/assets/js/widgets.bundle.js"></script>
    <script src="/metronic8/demo38/assets/js/custom/widgets.js"></script>
    <script src="/metronic8/demo38/assets/js/custom/apps/chat/chat.js"></script>
    <script src="/metronic8/demo38/assets/js/custom/utilities/modals/upgrade-plan.js"></script>
    <!--end::Custom Javascript-->
    <!--end::Javascript-->
  </body>
  <!--end::Body-->
</html>
