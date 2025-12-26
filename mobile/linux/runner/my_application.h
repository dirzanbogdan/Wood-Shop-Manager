#ifndef FLUTTER_MY_APPLICATION_H_
#define FLUTTER_MY_APPLICATION_H_

#if defined(__has_include)
#  if __has_include(<gtk/gtk.h>)
#    include <gtk/gtk.h>
#    define WSM_HAS_GTK 1
#  else
#    define WSM_HAS_GTK 0
#  endif
#else
#  include <gtk/gtk.h>
#  define WSM_HAS_GTK 1
#endif

#if WSM_HAS_GTK
G_DECLARE_FINAL_TYPE(MyApplication,
                     my_application,
                     MY,
                     APPLICATION,
                     GtkApplication)
#else
typedef struct _MyApplication MyApplication;
#endif

/**
 * my_application_new:
 *
 * Creates a new Flutter-based application.
 *
 * Returns: a new #MyApplication.
 */
MyApplication* my_application_new();

#endif  // FLUTTER_MY_APPLICATION_H_
