<?php

namespace App\Providers;

use App\Contracts\Borrows\BorrowReminderLogRepository;
use App\Contracts\Borrows\DueSoonBorrowFinder;
use App\Contracts\Mail\TransactionalEmailClient;
use App\Repositories\Borrows\EloquentBorrowReminderLogRepository;
use App\Repositories\Borrows\EloquentDueSoonBorrowFinder;
use App\Services\Mail\SmtpTransactionalEmailClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TransactionalEmailClient::class, SmtpTransactionalEmailClient::class);
        $this->app->bind(BorrowReminderLogRepository::class, EloquentBorrowReminderLogRepository::class);
        $this->app->bind(DueSoonBorrowFinder::class, EloquentDueSoonBorrowFinder::class);
    }


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
