<?php

namespace App\Http\Controllers;

use App\Loan;
use App\Branch;
use App\Client;
use App\Payment;
use Carbon\Carbon;
use App\MonthlyPayment;
use Illuminate\Http\Request;

interface ViewTypes {
	const BusinessOffice = "BusinessOffice";
	const HeadQuarter = "HeadQuarter";
}
class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $allclients_count = Client::count();
        $clientintenure_count = Loan::where('status', '=', 1)->orwhere('status', '=', 3)->count();
        $clienttenurextended_count = Loan::where('status', '=', 3)->count();
        $profit = Loan::count();
        
        $outstanding = Payment::where('payment_status',0)->get();

        $savings = Payment::where('payment_purpose','=','savings')->get();
        $allsavings = $savings->sum('outstanding_payment');

        $profit = Loan::all();
        $monthlyprofit = Loan::whereYear('updated_at',date('Y'))->whereMonth('updated_at', date('m'))->sum('monthly_profit');
        $yearlyprofit = Loan::whereYear('updated_at', date('Y'))->sum('yearly_profit');
        $allprofits = $profit->sum('actual_profit');
        $companyvalue = $outstanding->sum('outstanding_payment');
        $monthlyreports = Payment::whereMonth('next_due_date', date('m'))->with('client','loan')->where('payment_status',0)->take(3)->Orderby('next_due_date','ASC')->get();
        $tenureextendeds = Loan::with('client','payment')->where('status','<>',2)->get();
        $viewType = ViewTypes::HeadQuarter;
        $defaulters = Loan::with(['client', 'payment'])
            ->whereHas('payment', function ($query) {
                $query->whereRaw('MONTH(next_due_date) > MONTH(CURRENT_DATE())')
                    ->where('payment_status', 0)->Orderby('next_due_date','ASC');
            })
            ->whereHas('client', function ($query) {
                $query->where('status', 'in tenure');
            })
            ->take(5)->get();
        $tenureextendeds = $tenureextendeds->filter(
            function($items){
                    if( Carbon::parse($items->disbursement_date)->addMonth($items->tenure)  <  Carbon::now() or $items->status == 3){
                        return $items; 
                    } 
            })->take(5);
        return view('dashboard', compact(
            'allclients_count',
            'clientintenure_count',
            'companyvalue',
            'allsavings',
            'allprofits',
            'monthlyreports',
            'monthlyprofit',
            'yearlyprofit',
            'clienttenurextended_count',
            'tenureextendeds',
            'defaulters',
        ));
    }
}