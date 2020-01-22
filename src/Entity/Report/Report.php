<?php

namespace App\Entity\Report;


class Report
{
	public $a;
    public $a1;
    
    //2
    public $b;
    
    //3
    public $c;
    
    //4
    public $d;
    
    //5
    public $e;
    
    //6
    public $f;

    //7
    public $g;
    
    //8
    public $h;
    
    //9
    public $i;
    
    //10
    public $j;
    
    //11
    public $k;
    
    //12
    public $l;
    
    //13
    public $m;
    
    public $n;
    
    //15
    public $o;
    
    //16
    public $p;
    public $q;
    public $r;
    public $s;
    public $t;
    public $u;
    public $v;
    public $w;
    public $x;
    public $y;
    public $z;
    
    public function recalculate()
    {
    	$this->a = $this->a1;
    	
    	//DAVČNO PRIZNANI PRIHODKI (1 – 2 + 3) 
    	$this->d = $this->a - $this->b + $this->c;
    	
    	//DAVČNO PRIZNANI ODHODKI (5 – 6 + 7) 
    	$this->h = $this->e - $this->f + $this->g;
    	
    	//RAZLIKA med davčno priznanimi prihodki in odhodki (4 – 8)
    	$this->i = $this->d - $this->h > 0 ? $this->d - $this->h : 0;
    	
    	//RAZLIKA med davčno priznanimi odhodki in prihodki (8 – 4)
    	$this->j = $this->h - $this->d > 0 ? $this->h - $this->d : 0 ;
    	
    	//DAVČNA OSNOVA (9 + 11 + 12) ali (11+12-10); če je > 0
    	$this->m = $this->i>0?($this->i + $this->k + $this->l):($this->k + $this->l - $this->j);
    	
    	//OSNOVA ZA DOHODNINO (izračun dohodnine na letni ravni) (13-15)
    	$this->p = $this->m - $this->o;    	
    }
}
