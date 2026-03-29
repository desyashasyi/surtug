<?php

use App\Http\Controllers\Auth\CasController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ─── Root ────────────────────────────────────────────────────────────────────
Route::get('/', function () {
    if (!auth()->check()) return redirect()->route('login');
    $user = auth()->user();
    if ($user->hasRole('super-admin')) return redirect()->route('super-admin.idx');
    if ($user->hasRole('admin'))       return redirect()->route('admin.idx');
    return redirect()->route('program.idx');
});

// ─── Auth ────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::livewire('/login', 'pages::auth.⚡login')->name('login');
});

Route::get('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('login');
})->middleware('auth')->name('logout');

// ─── SSO CAS UPI ─────────────────────────────────────────────────────────────
Route::prefix('auth/cas')->name('auth.cas.')->group(function () {
    Route::get('/redirect', [CasController::class, 'redirect'])->name('redirect');
    Route::get('/callback', [CasController::class, 'callback'])->name('callback');
    Route::get('/logout',   [CasController::class, 'logout'])->name('logout')->middleware('auth');
});

// ─── Super Admin ──────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:super-admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::livewire('/',           'pages::super-admin.⚡idx')->name('idx');
    Route::livewire('/university', 'pages::super-admin.university.⚡idx')->name('university');
    Route::livewire('/faculty',    'pages::super-admin.faculty.⚡idx')->name('faculty');
    Route::livewire('/client',     'pages::super-admin.client.⚡idx')->name('client');
    Route::livewire('/user',       'pages::super-admin.user.⚡idx')->name('user');
});

// ─── Admin ────────────────────────────────────────────────────────────────────
Route::middleware(['auth', 'role:super-admin|admin'])->group(function () {
    Route::livewire('/admin',                      'pages::admin.⚡idx')->name('admin.idx');
    Route::livewire('/admin/program',              'pages::admin.program.⚡idx')->name('admin.program');
    Route::livewire('/admin/data/basic',           'pages::admin.data.basic.⚡idx')->name('admin.data.basic');
    Route::livewire('/admin/data/academic-year',   'pages::admin.data.academic-year.⚡idx')->name('admin.data.academic-year');
    Route::livewire('/admin/data/teachers',        'pages::admin.data.teachers.⚡idx')->name('admin.data.teachers');
    Route::livewire('/admin/data/space',           'pages::admin.data.space.⚡idx')->name('admin.data.space');
    Route::livewire('/admin/data/activities',      'pages::admin.data.activities.⚡idx')->name('admin.data.activities');
});

// ─── Program ──────────────────────────────────────────────────────────────────
Route::middleware(['auth'])->prefix('program')->name('program.')->group(function () {
    Route::livewire('/',                    'pages::program.⚡idx')->name('idx');
    Route::livewire('/data/specialization', 'pages::program.data.specialization.⚡idx')->name('data.specialization');
    Route::livewire('/data/subjects',       'pages::program.data.subjects.⚡idx')->name('data.subjects');
    Route::livewire('/data/teachers',       'pages::program.data.teachers.⚡idx')->name('data.teachers');
    Route::livewire('/data/students',       'pages::program.data.students.⚡idx')->name('data.students');
    Route::livewire('/data/activities',     'pages::program.data.activities.⚡idx')->name('data.activities');
    // Time constraints
    Route::livewire('/time/teachers',       'pages::program.time.teachers.⚡idx')->name('time.teachers');
    Route::livewire('/time/students',       'pages::program.time.students.⚡idx')->name('time.students');
    Route::livewire('/time/activities',     'pages::program.time.activities.⚡idx')->name('time.activities');
    // Space constraints
    Route::livewire('/space/teachers',      'pages::program.space.teachers.⚡idx')->name('space.teachers');
    Route::livewire('/space/students',      'pages::program.space.students.⚡idx')->name('space.students');
    Route::livewire('/space/activities',    'pages::program.space.activities.⚡idx')->name('space.activities');
    // Timetable
    Route::livewire('/timetable/teachers',  'pages::program.timetable.teachers.⚡idx')->name('timetable.teachers');
    Route::livewire('/timetable/students',  'pages::program.timetable.students.⚡idx')->name('timetable.students');
});
