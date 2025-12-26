#if defined(__has_include) && __has_include(<cstdlib>)
#include <cstdlib>
#else
#ifndef EXIT_SUCCESS
#define EXIT_SUCCESS 0
#endif
#ifndef EXIT_FAILURE
#define EXIT_FAILURE 1
#endif
#endif

#if defined(__has_include)
#  if __has_include(<windows.h>)
#    include <windows.h>
#    define WSM_HAS_WINDOWS_H 1
#  else
#    define WSM_HAS_WINDOWS_H 0
#    define APIENTRY
#    define _In_
#    define _In_opt_
typedef void* HINSTANCE;
#  endif
#else
#  include <windows.h>
#  define WSM_HAS_WINDOWS_H 1
#endif

#if WSM_HAS_WINDOWS_H
#  if defined(__has_include)
#    if __has_include(<flutter/dart_project.h>) && __has_include(<flutter/flutter_view_controller.h>)
#      include <flutter/dart_project.h>
#      include <flutter/flutter_view_controller.h>
#      define WSM_HAS_FLUTTER_WIN 1
#    else
#      define WSM_HAS_FLUTTER_WIN 0
#    endif
#  else
#    include <flutter/dart_project.h>
#    include <flutter/flutter_view_controller.h>
#    define WSM_HAS_FLUTTER_WIN 1
#  endif
#else
#  define WSM_HAS_FLUTTER_WIN 0
#endif

#if WSM_HAS_FLUTTER_WIN
#include "flutter_window.h"
#include "utils.h"

int APIENTRY wWinMain(_In_ HINSTANCE instance, _In_opt_ HINSTANCE prev,
                      _In_ wchar_t *command_line, _In_ int show_command) {
  // Attach to console when present (e.g., 'flutter run') or create a
  // new console when running with a debugger.
  if (!::AttachConsole(ATTACH_PARENT_PROCESS) && ::IsDebuggerPresent()) {
    CreateAndAttachConsole();
  }

  // Initialize COM, so that it is available for use in the library and/or
  // plugins.
  ::CoInitializeEx(nullptr, COINIT_APARTMENTTHREADED);

  flutter::DartProject project(L"data");

  std::vector<std::string> command_line_arguments =
      GetCommandLineArguments();

  project.set_dart_entrypoint_arguments(std::move(command_line_arguments));

  FlutterWindow window(project);
  Win32Window::Point origin(10, 10);
  Win32Window::Size size(1280, 720);
  if (!window.Create(L"WSM", origin, size)) {
    return EXIT_FAILURE;
  }
  window.SetQuitOnClose(true);

  ::MSG msg;
  while (::GetMessage(&msg, nullptr, 0, 0)) {
    ::TranslateMessage(&msg);
    ::DispatchMessage(&msg);
  }

  ::CoUninitialize();
  return EXIT_SUCCESS;
}
#else
int APIENTRY wWinMain(_In_ HINSTANCE instance, _In_opt_ HINSTANCE prev,
                      _In_ wchar_t* command_line, _In_ int show_command) {
  (void)instance;
  (void)prev;
  (void)command_line;
  (void)show_command;
  return EXIT_FAILURE;
}
#endif
