import { useEffect, useMemo, useState } from 'react'
import {
  cancelAdminSchedule,
  createAdminSchedule,
  getAdminSchedules,
  getMemberSchedules,
  getSchedules,
  updateAdminSchedule,
} from './lib/api'
import heroImage from './assets/hero.png'

const emptyForm = {
  title: 'Gym Hours',
  day_label: '',
  start_time: '06:00',
  end_time: '21:00',
  location: 'Main gym',
  notes: '',
  status: 'scheduled',
  is_visible: true,
  sort_order: 0,
}

function readSchedules(response) {
  return response?.data?.schedules || response?.schedules || []
}

function formatTime(value) {
  return value ? value.slice(0, 5) : ''
}

function ScheduleList({ schedules, compact = false }) {
  if (!schedules.length) {
    return (
      <p className="rounded-md border border-slate-200 bg-white/80 px-4 py-3 text-sm text-slate-600">
        Schedule will be announced soon.
      </p>
    )
  }

  return (
    <div className={compact ? 'space-y-3' : 'grid gap-4 md:grid-cols-2'}>
      {schedules.map((schedule) => (
        <article key={schedule.id} className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
          <div className="flex items-start justify-between gap-3">
            <div>
              <h3 className="text-base font-semibold text-slate-950">{schedule.title}</h3>
              <p className="mt-1 text-sm font-medium text-emerald-700">{schedule.day_label}</p>
            </div>
            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">
              {schedule.time_range || `${formatTime(schedule.start_time)} - ${formatTime(schedule.end_time)}`}
            </span>
          </div>
          {schedule.location ? <p className="mt-3 text-sm text-slate-600">{schedule.location}</p> : null}
          {schedule.notes ? <p className="mt-2 text-sm text-slate-500">{schedule.notes}</p> : null}
        </article>
      ))}
    </div>
  )
}

function LandingPage() {
  const [schedules, setSchedules] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    getSchedules()
      .then((data) => setSchedules(readSchedules(data)))
      .catch((err) => setError(err.message))
  }, [])

  return (
    <main className="min-h-screen bg-slate-50 text-slate-950">
      <section className="relative flex min-h-[74vh] items-center overflow-hidden bg-slate-950 text-white">
        <img className="absolute inset-0 h-full w-full object-cover opacity-45" src={heroImage} alt="" />
        <div className="relative mx-auto w-full max-w-6xl px-6 py-20">
          <p className="text-sm font-semibold uppercase tracking-wide text-emerald-300">DBU Gym Management</p>
          <h1 className="mt-4 max-w-3xl text-5xl font-bold leading-tight md:text-7xl">Train on a schedule that stays current.</h1>
          <p className="mt-6 max-w-2xl text-lg text-slate-200">
            Members and guests can now see the latest gym operating hours directly from the admin-controlled schedule.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <a className="rounded-md bg-emerald-400 px-5 py-3 text-sm font-semibold text-slate-950" href="/members/dashboard">
              Member dashboard
            </a>
            <a className="rounded-md border border-white/30 px-5 py-3 text-sm font-semibold text-white" href="/admin/dashboard">
              Admin schedule
            </a>
          </div>
        </div>
      </section>

      <footer className="bg-white">
        <div className="mx-auto grid max-w-6xl gap-8 px-6 py-12 md:grid-cols-[1fr_1.4fr]">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">Current schedule</p>
            <h2 className="mt-2 text-2xl font-bold text-slate-950">Gym hours</h2>
            {error ? <p className="mt-3 text-sm text-red-600">{error}</p> : null}
          </div>
          <ScheduleList schedules={schedules} compact />
        </div>
      </footer>
    </main>
  )
}

function MemberDashboard() {
  const [schedules, setSchedules] = useState([])
  const [error, setError] = useState('')

  useEffect(() => {
    getMemberSchedules()
      .then((data) => setSchedules(readSchedules(data)))
      .catch(() => getSchedules().then((data) => setSchedules(readSchedules(data))).catch((err) => setError(err.message)))
  }, [])

  return (
    <main className="min-h-screen bg-slate-50 px-6 py-10 text-slate-950">
      <div className="mx-auto max-w-6xl">
        <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">Members</p>
            <h1 className="mt-2 text-3xl font-bold">Training schedule</h1>
          </div>
          <a className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold" href="/">
            Back home
          </a>
        </div>
        {error ? <p className="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{error}</p> : null}
        <ScheduleList schedules={schedules} />
      </div>
    </main>
  )
}

function AdminDashboard() {
  const [schedules, setSchedules] = useState([])
  const [form, setForm] = useState(emptyForm)
  const [editingId, setEditingId] = useState(null)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')

  const sortedSchedules = useMemo(
    () => [...schedules].sort((a, b) => Number(a.sort_order) - Number(b.sort_order)),
    [schedules]
  )

  const loadSchedules = async () => {
    const data = await getAdminSchedules()
    setSchedules(readSchedules(data))
  }

  useEffect(() => {
    Promise.resolve()
      .then(loadSchedules)
      .catch((err) => setError(err.message))
  }, [])

  const updateField = (event) => {
    const { name, value, type, checked } = event.target
    setForm((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }))
  }

  const edit = (schedule) => {
    setEditingId(schedule.id)
    setForm({
      title: schedule.title || 'Gym Hours',
      day_label: schedule.day_label || '',
      start_time: formatTime(schedule.start_time) || '06:00',
      end_time: formatTime(schedule.end_time) || '21:00',
      location: schedule.location || '',
      notes: schedule.notes || '',
      status: schedule.status || 'scheduled',
      is_visible: Boolean(schedule.is_visible),
      sort_order: Number(schedule.sort_order || 0),
    })
  }

  const reset = () => {
    setEditingId(null)
    setForm(emptyForm)
  }

  const submit = async (event) => {
    event.preventDefault()
    setError('')
    setMessage('')

    try {
      if (editingId) {
        await updateAdminSchedule(editingId, form)
        setMessage('Schedule updated.')
      } else {
        await createAdminSchedule(form)
        setMessage('Schedule created.')
      }
      reset()
      await loadSchedules()
    } catch (err) {
      setError(err.message)
    }
  }

  const cancel = async (id) => {
    setError('')
    setMessage('')
    try {
      await cancelAdminSchedule(id)
      setMessage('Schedule cancelled.')
      await loadSchedules()
    } catch (err) {
      setError(err.message)
    }
  }

  return (
    <main className="min-h-screen bg-slate-100 px-6 py-8 text-slate-950">
      <div className="mx-auto grid max-w-7xl gap-6 lg:grid-cols-[420px_1fr]">
        <section className="rounded-lg bg-white p-5 shadow-sm">
          <div className="mb-5 flex items-center justify-between gap-3">
            <div>
              <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">Admin</p>
              <h1 className="text-2xl font-bold">{editingId ? 'Edit schedule' : 'New schedule'}</h1>
            </div>
            <a className="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" href="/">
              Home
            </a>
          </div>

          <form className="space-y-4" onSubmit={submit}>
            <label className="block text-sm font-medium">
              Title
              <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" name="title" value={form.title} onChange={updateField} required />
            </label>
            <label className="block text-sm font-medium">
              Day label
              <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" name="day_label" value={form.day_label} onChange={updateField} placeholder="Monday - Friday" required />
            </label>
            <div className="grid grid-cols-2 gap-3">
              <label className="block text-sm font-medium">
                Start
                <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" type="time" name="start_time" value={form.start_time} onChange={updateField} required />
              </label>
              <label className="block text-sm font-medium">
                End
                <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" type="time" name="end_time" value={form.end_time} onChange={updateField} required />
              </label>
            </div>
            <label className="block text-sm font-medium">
              Location
              <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" name="location" value={form.location} onChange={updateField} />
            </label>
            <label className="block text-sm font-medium">
              Notes
              <textarea className="mt-1 min-h-24 w-full rounded-md border border-slate-300 px-3 py-2" name="notes" value={form.notes} onChange={updateField} />
            </label>
            <div className="grid grid-cols-2 gap-3">
              <label className="block text-sm font-medium">
                Status
                <select className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" name="status" value={form.status} onChange={updateField}>
                  <option value="scheduled">Scheduled</option>
                  <option value="completed">Completed</option>
                  <option value="cancelled">Cancelled</option>
                </select>
              </label>
              <label className="block text-sm font-medium">
                Sort order
                <input className="mt-1 w-full rounded-md border border-slate-300 px-3 py-2" type="number" min="0" name="sort_order" value={form.sort_order} onChange={updateField} />
              </label>
            </div>
            <label className="flex items-center gap-2 text-sm font-medium">
              <input className="h-4 w-4" type="checkbox" name="is_visible" checked={form.is_visible} onChange={updateField} />
              Visible on landing footer and member page
            </label>

            {error ? <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">{error}</p> : null}
            {message ? <p className="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{message}</p> : null}

            <div className="flex gap-3">
              <button className="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">
                {editingId ? 'Update schedule' : 'Create schedule'}
              </button>
              {editingId ? (
                <button className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold" type="button" onClick={reset}>
                  Cancel edit
                </button>
              ) : null}
            </div>
          </form>
        </section>

        <section className="rounded-lg bg-white p-5 shadow-sm">
          <h2 className="mb-4 text-xl font-bold">Managed schedules</h2>
          <div className="space-y-3">
            {sortedSchedules.map((schedule) => (
              <article key={schedule.id} className="rounded-lg border border-slate-200 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h3 className="font-semibold">{schedule.title}</h3>
                    <p className="text-sm text-slate-600">
                      {schedule.day_label} | {schedule.time_range}
                    </p>
                    <p className="mt-1 text-xs uppercase tracking-wide text-slate-500">
                      {schedule.status} | {schedule.is_visible ? 'visible' : 'hidden'}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    <button className="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold" type="button" onClick={() => edit(schedule)}>
                      Edit
                    </button>
                    <button className="rounded-md border border-red-200 px-3 py-2 text-sm font-semibold text-red-700" type="button" onClick={() => cancel(schedule.id)}>
                      Cancel
                    </button>
                  </div>
                </div>
              </article>
            ))}
            {!sortedSchedules.length ? <p className="text-sm text-slate-600">No schedules yet.</p> : null}
          </div>
        </section>
      </div>
    </main>
  )
}

export default function App() {
  const path = window.location.pathname

  if (path.startsWith('/admin')) return <AdminDashboard />
  if (path.startsWith('/members')) return <MemberDashboard />

  return <LandingPage />
}
