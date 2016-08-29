<?php

namespace App\Http\Controllers;

// use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Request;
use Validator;
use Session;
use Html, Form;
use App\Obat;
use App\Transaksi;
use UxWeb\SweetAlert\SweetAlert;
use PDF;
use DB;
use App\User;
use Auth;
use App\Rak;

class LaporanCtrl extends Controller
{
    public function penjualan()
    {
        $this->checkAcl();

    	return view('laporan.penjualan');
    }

    public function halamanCetak()
    {
    	return view('pdf.halamancetak');
    }

    public function harian()
    {
    	$tgl = date('Y-m-d');

    	// $ambil = DB::select("select t.*, o.id, o.nama_obat from transaksi t, obat o where t.obat_id = o.id and date(t.created_at) = '$tgl'");
    	$ambil = DB::table('transaksi')
    			->join('obat', 'transaksi.obat_id', '=', 'obat.id')
    			->select('transaksi.*', 'obat.nama_obat')
    			->whereRaw("date(transaksi.created_at) = '$tgl'")
    			->get();

    	$pdf = PDF::loadView('pdf.harian', compact('ambil'))
    	->setPaper('a4')
    	->setWarnings(false);
    	// ->setOrientation('landscape');
    	return $pdf->download(date('Y-m-d').'-laporan_bulanan.pdf');
    	// return 'harian';

    	// echo "<pre>";
    	// print_r($ambil);
    	// echo "</pre>";

    }

    public function mingguan()
    {
    	$rules = ['tglawal'=>'required|date', 'tglakhir'=>'required|date'];
    	$tglawal=Request::input('tglawal');
    	$tglakhir=Request::input('tglakhir');

    	$validator = Validator::make(Request::all(), $rules);

    	if ($validator->fails()) {
    		return redirect()->back()->withInput()->withErrors($validator);
    	} 
    	
    	$ambil = DB::table('transaksi')
    			->join('obat', 'transaksi.obat_id', '=', 'obat.id')
    			->select('transaksi.*', 'obat.nama_obat')
    			->whereRaw("date(transaksi.created_at) between '$tglawal' and '$tglakhir'")
    			->get();

    	$pdf = PDF::loadView('pdf.mingguan', compact('ambil', 'tglawal', 'tglakhir'))
    	->setPaper('a4')
    	->setWarnings(false);
    	// ->setOrientation('landscape');
    	return $pdf->download(date('Y-m-d').'-laporan_mingguan.pdf');
    	// return 'harian';

    	// echo "<pre>";
    	// print_r($ambil);
    	// echo "</pre>";
    	// return 'mingguan';
    }

    public function bulanan()
    {
    	$rules = ['tglbulanan'=>'required|date_format:m'];

    	$tglbulanan = Request::input('tglbulanan');

    	$validator = Validator::make(Request::all(), $rules);

    	if ($validator->fails()) {
    		return redirect()->back()->withInput()->withErrors($validator);
    	} 
    	
    	$ambil = DB::table('transaksi')
    			->join('obat', 'transaksi.obat_id', '=', 'obat.id')
    			->select('transaksi.*', 'obat.nama_obat')
    			->whereRaw("month(transaksi.created_at) = '$tglbulanan'")
    			->get();

    	$pdf = PDF::loadView('pdf.bulanan', compact('ambil', 'tglbulanan'))
    	->setPaper('a4')
    	->setWarnings(false);
    	// ->setOrientation('landscape');
    	return $pdf->download(date('Y-m-d').'-bulanan.pdf');

    	// echo "<pre>";
    	// print_r($ambil);
    	// echo "</pre>";
    }

    public function chartLaris()
    {
        $tgl = date('Y-m-d');
        $bln = date('m');

        $charts = DB::table('transaksi')
                    ->join('obat', 'transaksi.obat_id', '=', 'obat.id')
                    ->select('obat.nama_obat', DB::raw('count(transaksi.obat_id) as obat_count'))
                    ->whereRaw("date(transaksi.created_at) = '$tgl'")
                    ->groupBy('transaksi.obat_id')
                    ->get();

        $charts_bln = DB::table('transaksi')
                    ->join('obat', 'transaksi.obat_id', '=', 'obat.id')
                    ->select('obat.nama_obat as nama_obat_bln', DB::raw('count(transaksi.obat_id) as obat_count_bln'))
                    ->whereRaw("month(transaksi.created_at) = '$bln'")
                    ->groupBy('transaksi.obat_id')
                    ->orderBy('obat_count_bln', 'desc')
                    ->take(100)
                    ->get();

        // echo "<pre>";
        // print_r($charts);
        // echo "</pre>";
        return view('chart.chart-laris', compact('charts', 'charts_bln'));
    }

    // laporan obat habis
    public function obatHabis()
    {
        $obathabis = Obat::where('isi', 0)->get();
        // return dd($obathabis);
        return view('laporan.obathabis', compact('obathabis'));
    }

    // hitung total transaksi hari ini.
    public function countTotal()
    {
        $this->checkAcl();
        
        $tgl = date('Y-m-d');
        $total = 0;

        // $ambil = DB::select("select t.*, o.id, o.nama_obat from transaksi t, obat o where t.obat_id = o.id and date(t.created_at) = '$tgl'");
        $totalpend = DB::table('transaksi')
                // ->join('obat', 'transaksi.obat_id', '=', 'obat.id')
                ->select(DB::raw('sum(total_harga) as totalpendapatan'))
                // ->sum('total_harga')
                ->whereRaw("date(transaksi.created_at) = '$tgl'")
                ->get();

        
        foreach ($totalpend as $key) {
            $total = $key->totalpendapatan;
        }

        /*hitung pendapatan per user*/
        $totalperuser = DB::table('transaksi')
                ->join('users', 'transaksi.user_id', '=', 'users.id')
                ->select('users.name', DB::raw('sum(total_harga) as totalpendapatan'))
                // ->sum('total_harga')
                ->whereRaw("date(transaksi.created_at) = '$tgl'")
                ->groupBy('transaksi.user_id')
                ->get();

        return view('pdf.informasi_pendapatan', compact('total', 'totalperuser'));
        // echo "<pre>";
        // print_r($totalperuser);
        // echo "</pre>";
    }

    // informasi obat
    public function infoObat()
    {
        $obatdata = Obat::select('nama_obat', 'golongan', 'merk', 'rak', 'diskon', 'harga_pokok', 'harga_jual', 'kadaluarsa')->get();
        // $obatdata = e($obatdata);
        $pdf = PDF::loadView('pdf.infoobat', compact('obatdata'))
        ->setPaper('a4')
        ->setWarnings(true);
        // ->setOrientation('landscape');
        return $pdf->stream(date('Y-m-d').'-laporan_obat.pdf');
    }

    // get cetak per rak 
    public function getCetakPerRak()
    {
        $rak = Rak::lists('nama_rak', 'id');
        return view('pdf.viewperrak', compact('rak'));
    }

    // post cetak per rak 
    public function postCetakPerRak()
    {
        $rak = Request::input('rak');

        $obatdata = Obat::where('rak', '=', $rak)->get();
        $pdf = PDF::loadView('pdf.infoobat', compact('obatdata'))
            ->setPaper('a4')
            ->setWarnings(false);
        return $pdf->stream(date('Y-m-d').'-laporan_obat_per_rak.pdf');
    }

    // check Acl
    public function checkAcl()
    {
        $id = Auth::user()->id;
        $user = User::find($id);
        if ($user->is('apoteker')) {
            abort(401);
        }
    }

}
